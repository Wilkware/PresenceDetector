<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\PresenceDetector\DebugHelper;
use Wilkware\PresenceDetector\EventHelper;
use Wilkware\PresenceDetector\VariableHelper;

/**
 * CLASS PresenceDetector
 */
class PresenceDetector extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use VariableHelper;

    // -------------------------------------------------------------------------
    // Schedule Constants
    // -------------------------------------------------------------------------

    /** @var string Schedule name */
    private const SCHEDULE_NAME = 'Zeitplan';

    /** @var string Schedule identifier */
    private const SCHEDULE_IDENT = 'circuit_diagram';

    /** @var array<int,mixed> Schedule switch options */
    private const SCHEDULE_SWITCH = [
        1 => ['Aktiv', 0x00FF00, ''],
        2 => ['Inaktiv', 0xFF0000, ''],
    ];

    // -------------------------------------------------------------------------
    // Device Constants
    // -------------------------------------------------------------------------

    /** @var int Device one */
    private const DEVICE_ONE = 0;

    /** @var int Device multiple */
    private const DEVICE_MULTIPLE = 1;

    /** @var int Logical constant */
    private const LINK_AND = 0;

    /** @var int Logical constant */
    private const LINK_OR = 1;

    /** @var int Logical constant */
    private const LINK_NOT = 2;

    /** @var int Sensor delay time frame (milliseconds) */
    private const DELAY_TIME = 500;

    /** @var int Min IPS Object ID */
    private const IPS_MIN_ID = 10000;

    // -------------------------------------------------------------------------
    // Presentations
    // -------------------------------------------------------------------------

    /**
     * @var array<string,mixed> Presentation (type)
     */
    private const TPD_PRESENTATION_THREADHOLD = [
        'USAGE_TYPE'          => 2,
        'THOUSANDS_SEPARATOR' => '',
        'DECIMAL_SEPARATOR'   => 'Client',
        'PERCENTAGE'          => false,
        'DIGITS'              => 0,
        'INTERVALS'           => '[]',
        'ICON'                => 'brightness-low',
        'INTERVALS_ACTIVE'    => false,
        'MAX'                 => 500,
        'GRADIENT_TYPE'       => 0,
        'MIN'                 => 0,
        'CUSTOM_GRADIENT'     => '[]',
        'PREFIX'              => '',
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'STEP_SIZE'           => 0.0,
        'SUFFIX'              => ' lx',
    ];

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Sensors
        $this->RegisterPropertyInteger('LogicalLink', self::LINK_OR);
        $this->RegisterPropertyInteger('MotionVariable', 1);
        $this->RegisterPropertyInteger('SensorVariable', 1);
        $this->RegisterPropertyInteger('SensorDelay', self::DELAY_TIME);
        $this->RegisterPropertyInteger('BrightnessVariable', 1);
        $this->RegisterPropertyInteger('ThresholdValue', 0);

        //Schedule
        $this->RegisterPropertyInteger('EventVariable', 1);

        // Device
        $this->RegisterPropertyInteger('DeviceNumber', 0);
        $this->RegisterPropertyInteger('SwitchVariable', 1);
        $this->RegisterPropertyString('SwitchVariables', '[]');
        $this->RegisterPropertyInteger('ScriptVariable', 1);

        // Settings
        $this->RegisterPropertyBoolean('ThresholdVariable', false);
        $this->RegisterPropertyBoolean('ExecuteAlways', false);

        // Attribute
        $this->RegisterAttributeInteger('Trigger', 0);

        // Timer
        $this->RegisterTimer('TPD.Timer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "DelayTrigger", "");');
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // number of devices
        $number = $this->ReadPropertyInteger('DeviceNumber');
        $form['elements'][4]['items'][1]['visible'] = ($number === self::DEVICE_ONE);
        $form['elements'][4]['items'][2]['visible'] = ($number === self::DEVICE_MULTIPLE);
        // device list (set status column)
        $variables = json_decode($this->ReadPropertyString('SwitchVariables'), true);
        foreach ($variables as $variable) {
            $form['elements'][4]['items'][2]['values'][] = [
                'Status' => $this->GetVariableStatus($variable['VariableID']),
            ];
        }
        // return form
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        //Register references
        $variable = $this->ReadPropertyInteger('MotionVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }
        $variable = $this->ReadPropertyInteger('SensorVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }
        $variable = $this->ReadPropertyInteger('BrightnessVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (IPS_EventExists($event)) {
            $this->RegisterReference($event);
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (IPS_ScriptExists($script)) {
            $this->RegisterReference($script);
        }
        $variables = json_decode($this->ReadPropertyString('SwitchVariables'), true);
        foreach ($variables as $variable) {
            if (IPS_VariableExists($variable['VariableID'])) {
                $this->RegisterReference($variable['VariableID']);
            }
        }
        $variable = $this->ReadPropertyInteger('SwitchVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
        }

        //Safty checks
        $this->SetTimerInterval('TPD.Timer', 0);
        $this->WriteAttributeInteger('Trigger', 1);

        $variable = $this->ReadPropertyInteger('MotionVariable');
        if (($variable >= self::IPS_MIN_ID) && !IPS_VariableExists($variable)) {
            $this->LogDebug(__FUNCTION__, 'MotionVariable: ' . $variable);
            $this->SetStatus(104);
            return;
        }
        $variable = $this->ReadPropertyInteger('SensorVariable');
        if (($variable >= self::IPS_MIN_ID) && !IPS_VariableExists($variable)) {
            $this->LogDebug(__FUNCTION__, 'SensorVariable: ' . $variable);
            $this->SetStatus(104);
            return;
        }
        $variable = $this->ReadPropertyInteger('BrightnessVariable');
        if (($variable >= self::IPS_MIN_ID) && !IPS_VariableExists($variable)) {
            $this->LogDebug(__FUNCTION__, 'BrightnessVariable: ' . $variable);
            $this->SetStatus(104);
            return;
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (($event >= self::IPS_MIN_ID) && !IPS_EventExists($event)) {
            $this->LogDebug(__FUNCTION__, 'EventVariable: ' . $event);
            $this->SetStatus(104);
            return;
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (($script >= self::IPS_MIN_ID) && !IPS_ScriptExists($script)) {
            $this->LogDebug(__FUNCTION__, 'ScriptVariable: ' . $script);
            $this->SetStatus(104);
            return;
        }

        $number = $this->ReadPropertyInteger('DeviceNumber');
        if ($number == self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('SwitchVariable');
            if (!IPS_VariableExists($variable)) {
                $this->LogDebug(__FUNCTION__, 'SwitchVariable: ' . $variable);
                $this->SetStatus(104);
                return;
            }
        } else {
            $ok = 0;
            foreach ($variables as $variable) {
                if ($this->GetVariableStatus($variable['VariableID']) == 'OK') {
                    $ok++;
                }
            }
            if ((empty($variables)) || ($ok != count($variables))) {
                $this->LogDebug(__FUNCTION__, 'SwitchVariables: ' . $ok);
                $this->SetStatus(104);
                return;
            }
        }

        //Register update messages = Create our trigger
        if (IPS_VariableExists($this->ReadPropertyInteger('MotionVariable'))) {
            $this->RegisterMessage($this->ReadPropertyInteger('MotionVariable'), VM_UPDATE);
        }
        if (IPS_VariableExists($this->ReadPropertyInteger('SensorVariable'))) {
            $this->RegisterMessage($this->ReadPropertyInteger('SensorVariable'), VM_UPDATE);
        }

        // Threshold
        $threshold = $this->ReadPropertyBoolean('ThresholdVariable');
        $this->MaintainVariable('BrightnessThreshold', $this->Translate('Brightness threshold'), VARIABLETYPE_INTEGER, self::TPD_PRESENTATION_THREADHOLD, 2, $threshold);
        if ($threshold) {
            $this->EnableAction('BrightnessThreshold');
        }
        $this->SetStatus(102);
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     *
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        switch ($message) {
            case VM_UPDATE:
                // Safety Check
                $id1 = $this->ReadPropertyInteger('MotionVariable');
                $id2 = $this->ReadPropertyInteger('SensorVariable');
                if (($sender != $id1) && ($sender != $id2)) {
                    $this->LogDebug(__FUNCTION__, $sender . ' unknown!');
                    break;
                }
                // OnChange on TRUE, i.e. motion detected
                if ($data[0] == true && $data[1] == true) {
                    $this->LogDebug(__FUNCTION__, 'OnChange on TRUE - motion detected ' . $sender);
                    $this->ProcessData($sender, $id1, $id2);
                } elseif ($data[0] == false && $data[1] == true) { // OnChange on FALSE, i.e. no motion
                    $this->LogDebug(__FUNCTION__, 'OnChange on FALSE - no motion');
                } else { // OnChange on FALSE, i.e. no change of status
                    $this->LogDebug(__FUNCTION__, 'OnChange unchanged - status not changed');
                }
                break;
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'BrightnessThreshold':
                $this->SetValueInteger($ident, $value);
                break;
            case 'DelayTrigger':
                $this->ProcessData();
                break;
            default:
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
    }

    /**
     * User has select an new number of devices.
     *
     * @param int $value select value.
     *
     * @return void
     */
    protected function OnDeviceNumber(int $value): void
    {
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value);
        $this->UpdateFormField('SwitchVariable', 'visible', ($value == self::DEVICE_ONE));
        $this->UpdateFormField('SwitchVariables', 'visible', ($value == self::DEVICE_MULTIPLE));
    }

    /**
     * Creates a schedule plan.
     *
     * @param string $value instance ID.
     *
     * @return void
     */
    protected function OnCreateSchedule($value): void
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if (IPS_EventExists($eid)) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * Main entry point for the whole switch process.
     *
     * @param int $sender Sender ID
     * @param int $id1 Motion sensor ID 1
     * @param int $id2 Motion sensor ID 2
     *
     * @return void
     */
    private function ProcessData(int $sender = -1, int $id1 = 1, int $id2 = 1): void
    {
        $this->LogDebug(__FUNCTION__, 'Sender: ' . $sender . ', Sensoren = [ ' . $id1 . ', ' . $id2 . ']');
        // first step is to check logical link
        $check = $this->CheckLink($sender, $id1, $id2);
        $this->LogDebug(__FUNCTION__, 'CheckLink: ' . var_export($check, true));
        // next step is to check weekly schedule
        $check = $check && $this->CheckSchedule();
        $this->LogDebug(__FUNCTION__, 'CheckSchedule: ' . var_export($check, true));
        // next step is to check brightness
        $check = $check && $this->CheckBrightness();
        $this->LogDebug(__FUNCTION__, 'CheckBrightness: ' . var_export($check, true));
        // next step is to switch devices
        if ($check) {
            $this->SwitchDevices();
        }
        // last step is the script execution
        if ($check || $this->ReadPropertyBoolean('ExecuteAlways')) {
            $this->ExecuteScript();
        }
    }

    /**
     * Check logical link
     *
     * @param int $sender Sender ID
     * @param int $id1 Motion sensor ID 1
     * @param int $id2 Motion sensor ID 2
     *
     * @return bool true if check successful; otherwise false.
     */
    private function CheckLink(int $sender, int $id1, int $id2): bool
    {
        // Exist logical link
        if (($sender != -1) && (($id1 < self::IPS_MIN_ID) || ($id2 < self::IPS_MIN_ID))) {
            $this->LogDebug(__FUNCTION__, 'no link - no check: ' . $id1 . ' : ' . $id2);
            return true; // no link no check
        }
        $this->LogDebug(__FUNCTION__, 'Sender: ' . $sender);
        // Check locical link
        $link = $this->ReadPropertyInteger('LogicalLink');
        $time = $this->ReadPropertyInteger('SensorDelay');
        switch ($link) {
            case self::LINK_AND: // AND
                // Timer expired ?
                if ($sender == -1) {
                    break;
                }
                // check pre condition
                $trigger = $this->ReadAttributeInteger('Trigger');
                $this->LogDebug(__FUNCTION__, 'Trigger (and): ' . $trigger);
                if (($trigger >= self::IPS_MIN_ID) && ($trigger != $sender)) {
                    $this->LogDebug(__FUNCTION__, 'AND condition fulfilled!');
                    // AND condition fulfilled => reset all => fire
                    $this->SetTimerInterval('TPD.Timer', 0);
                    $this->WriteAttributeInteger('Trigger', 1);
                    return true;
                }
                if ($trigger < self::IPS_MIN_ID) {
                    $this->LogDebug(__FUNCTION__, 'AND condition not fulfilled');
                    // AND condition not fulfilled => init all => wait
                    if ($time > 0) {
                        $this->SetTimerInterval('TPD.Timer', $time);
                        $this->WriteAttributeInteger('Trigger', $sender);
                    }
                    return false; // we wait
                }
                break;
            case self::LINK_OR: // OR
                // Timer expired ?
                if ($sender == -1) {
                    break;
                }
                // check pre condition
                $trigger = $this->ReadAttributeInteger('Trigger');
                $this->LogDebug(__FUNCTION__, 'Trigger (or): ' . $trigger);
                if ($trigger < self::IPS_MIN_ID) {
                    $this->LogDebug(__FUNCTION__, 'OR condition ');
                    // OR condition fulfilled => fire and wait to block reswitch
                    if ($time > 0) {
                        $this->SetTimerInterval('TPD.Timer', $time);
                        $this->WriteAttributeInteger('Trigger', $sender);
                    }
                    return true; // and wait
                }
                break;
            case self::LINK_NOT: // NOT
                $trigger = $this->ReadAttributeInteger('Trigger');
                $this->LogDebug(__FUNCTION__, 'Trigger (not): ' . $trigger);
                if (($sender == -1) && ($trigger >= self::IPS_MIN_ID)) {
                    $this->LogDebug(__FUNCTION__, 'NOT condition fulfilled!');
                    // NOR condition fulfilled => reset all => fire
                    $this->SetTimerInterval('TPD.Timer', 0);
                    $this->WriteAttributeInteger('Trigger', 1);
                    return true;
                }
                if (($sender >= self::IPS_MIN_ID) && ($trigger < self::IPS_MIN_ID)) {
                    $this->LogDebug(__FUNCTION__, 'NOR condition still fulfilled (wait)');
                    // NOR condition still fulfilled => init all => wait
                    if ($time > 0) {
                        $this->SetTimerInterval('TPD.Timer', $time);
                        $this->WriteAttributeInteger('Trigger', ($sender == $id1) ? $sender : 1);
                    }
                    return false; // we wait
                }
                break;
        }
        // Timer has expired => Reset all
        $this->LogDebug(__FUNCTION__, 'Timer has expired!');
        $this->SetTimerInterval('TPD.Timer', 0);
        $this->WriteAttributeInteger('Trigger', 1);
        return false;
    }

    /**
     * Check brightness condition
     *
     * @return bool true if check successful; otherwise false.
     */
    private function CheckBrightness(): bool
    {
        // Check brightness
        if ($this->ReadPropertyInteger('BrightnessVariable') >= self::IPS_MIN_ID) {
            $bv = GetValue($this->ReadPropertyInteger('BrightnessVariable'));
            $tv = $this->ReadPropertyInteger('ThresholdValue');
            if ($this->ReadPropertyBoolean('ThresholdVariable')) {
                $tv = $this->GetValue('BrightnessThreshold');
            }
            if (($tv != 0) && ($bv > $tv)) {
                $this->LogDebug(__FUNCTION__, 'Brightness: ' . $bv . ' above threshold: ' . $tv);
                return false; // nothing to do
            }
            $this->LogDebug(__FUNCTION__, 'Always or below threshold: ' . $bv . ' (Threshold: ' . $tv . ')');
        }
        return true;
    }

    /**
     * Check weekly schedule
     *
     * @return bool true if check successful; otherwise false.
     */
    private function CheckSchedule(): bool
    {
        // Check weekly schedule
        $eid = $this->ReadPropertyInteger('EventVariable');
        if ($eid >= self::IPS_MIN_ID) {
            $state = $this->GetWeeklyScheduleInfo($eid);
            if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                $this->LogDebug(__FUNCTION__, 'Schedule plan is inactiv!');
                return false; // nothing to do
            }
        }
        return true;
    }

    /**
     * Switch Devices if deposite
     *
     * @return void
     */
    private function SwitchDevices(): void
    {
        // Switch variable(s)
        $number = $this->ReadPropertyInteger('DeviceNumber');
        if ($number == self::DEVICE_ONE) {
            $dv = $this->ReadPropertyInteger('SwitchVariable');
            $this->LogDebug(__FUNCTION__, 'Switch only one device: ' . $dv);
            if ($dv >= self::IPS_MIN_ID) {
                $ret = @RequestAction($dv, true);
                if ($ret === false) {
                    $this->LogDebug(__FUNCTION__, 'Device #' . $dv . ' could not be switched by RequestAction!');
                    $ret = @SetValueBoolean($dv, true);
                    if ($ret === false) {
                        $this->LogDebug(__FUNCTION__, 'Device could not be switched by Boolean!');
                    }
                }
                if ($ret === false) {
                    $this->LogMessage('Device could not be switched (UNREACH)!');
                }
            }
        } else {
            $variables = json_decode($this->ReadPropertyString('SwitchVariables'), true);
            $ret = true;
            foreach ($variables as $variable) {
                $this->LogDebug(__FUNCTION__, 'Switch multible devices: ' . $variable['VariableID']);
                $ret = @RequestAction($variable['VariableID'], true);
                if ($ret === false) {
                    $this->LogDebug(__FUNCTION__, 'Device #' . $variable['VariableID'] . ' could not be switched by RequestAction!');
                    $ret = false;
                }
            }
            if ($ret === false) {
                $this->LogMessage('One or more devices could not be switched!');
            }
        }
    }

    /**
     * Executes the script if deposited
     *
     * @return void
     */
    private function ExecuteScript(): void
    {
        // Run script
        if ($this->ReadPropertyInteger('ScriptVariable') >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                $mv = $this->ReadPropertyInteger('MotionVariable');
                $sv = $this->ReadPropertyInteger('SensorVariable');
                if ($sv != 0) {
                    $mv = '' . $mv . ',' . $sv;
                }
                $bv = $this->ReadPropertyInteger('BrightnessVariable');
                $dv = $this->ReadPropertyInteger('SwitchVariable');
                $tv = $this->ReadPropertyInteger('ThresholdValue');
                if ($this->ReadPropertyBoolean('ThresholdVariable')) {
                    $tv = $this->GetValue('BrightnessThreshold');
                }
                $number = $this->ReadPropertyInteger('DeviceNumber');
                if ($number == self::DEVICE_MULTIPLE) {
                    $variables = json_decode($this->ReadPropertyString('SwitchVariables'), true);
                    $dv = implode(',', array_column($variables, 'VariableID'));
                }
                $ret = IPS_RunScriptEx(
                    $this->ReadPropertyInteger('ScriptVariable'),
                    ['MotionVariable' => $mv, 'BrightnessVariable' => $bv, 'SwitchVariable' => $dv, 'ThresholdValue' => $tv]
                );
                $this->LogDebug(__FUNCTION__, 'Script return value: ' . $ret);
            }
        }
    }

    /**
     * Received the status of a given variable
     *
     * @param int $vid variable ID.
     *
     * @return string status of the variable.
     */
    private function GetVariableStatus(int $vid): string
    {
        if (!IPS_VariableExists($vid)) {
            return $this->Translate('Missing');
        } else {
            $var = IPS_GetVariable($vid);
            switch ($var['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    if ($var['VariableCustomProfile'] != '') {
                        $profile = $var['VariableCustomProfile'];
                    } else {
                        $profile = $var['VariableProfile'];
                    }
                    if (!IPS_VariableProfileExists($profile)) {
                        return $this->Translate('Profile required');
                    }
                    if ($var['VariableCustomAction'] != 0) {
                        $action = $var['VariableCustomAction'];
                    } else {
                        $action = $var['VariableAction'];
                    }
                    if (!($action > 10000)) {
                        return $this->Translate('Action required');
                    }
                    return 'OK';
                default:
                    return $this->Translate('Bool required');
            }
        }
    }
}
