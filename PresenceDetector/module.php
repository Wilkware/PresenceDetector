<?php

declare(strict_types=1);

// General helper functions
require_once __DIR__ . '/../libs/_traits.php';

// Namespaced traits
use Wilkware\PresenceDetector\DebugHelper;
use Wilkware\PresenceDetector\EventHelper;
use Wilkware\PresenceDetector\FormHelper;
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
    use FormHelper;
    use VariableHelper;

    // -------------------------------------------------------------------------
    // Schedule Constants
    // -------------------------------------------------------------------------

    /** @var int Schedule ON */
    public const SCHEDULE_ON = 1;

    /** @var int Schedule OFF */
    public const SCHEDULE_OFF = 2;

    /** @var string Schedule Name */
    public const SCHEDULE_NAME = 'Schedule';

    /** @var string Schedule Identifier */
    public const SCHEDULE_IDENT = 'circuit_diagram';

    /** @var array<int,array<mixed>> Schedule Switch */
    public const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Active', 0x00FF00, ''],
        self::SCHEDULE_OFF => ['Inactive', 0xFF0000, ''],
    ];

    // -------------------------------------------------------------------------
    // Logical Link Constants
    // -------------------------------------------------------------------------

    /** @var int Logical link AND */
    private const LINK_AND = 0;

    /** @var int Logical link OR */
    private const LINK_OR = 1;

    /** @var int Logical link NOT */
    private const LINK_NOT = 2;

    /** @var array<int,string> Logical link names (untranslated) */
    private const LINK_NAMES = [
        self::LINK_AND => '∧ (and)',
        self::LINK_OR  => '∨ (or)',
        self::LINK_NOT => '¬ (not)',
    ];

    // -------------------------------------------------------------------------
    // Status Constants
    // -------------------------------------------------------------------------

    /** @var int Switch variable missing */
    private const STATUS_VARIABLE_MISSING = 201;

    /** @var int Switch variable not switchable */
    private const STATUS_VARIABLE_INVALID = 202;

    /** @var int Motion variable missing */
    private const STATUS_MOTION_MISSING = 203;

    /** @var int Brightness variable missing */
    private const STATUS_BRIGHTNESS_MISSING = 204;

    /** @var int Weekly schedule missing */
    private const STATUS_SCHEDULE_MISSING = 205;

    /** @var int Script missing */
    private const STATUS_SCRIPT_MISSING = 206;

    /** @var int Time frame required for AND/NOT link */
    private const STATUS_DELAY_REQUIRED = 207;

    // -------------------------------------------------------------------------
    // Presentation Constants
    // -------------------------------------------------------------------------

    /** @var array<string,mixed> Automatic (switch) */
    private const TPD_PRESENTATION_AUTOMATIC = [
        'ICON_TRUE'    => 'person-walking',
        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
    ];

    /** @var array<string,mixed> Brightness threshold (slider) */
    private const TPD_PRESENTATION_THRESHOLD = [
        'ICON'         => 'brightness-low',
        'MAX'          => 500,
        'MIN'          => 0,
        'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
        'STEP_SIZE'    => 1,
        'SUFFIX'       => ' lx',
    ];

    /** @var array<string,mixed> Last trigger (date and time); presentation has no icon */
    private const TPD_PRESENTATION_TIME = [
        'DATE'         => 1,
        'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
        'TIME'         => 2,
    ];

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var int Device Type: Single */
    private const DEVICE_ONE = 0;

    /** @var int Device Type: Multiple */
    private const DEVICE_MULTIPLE = 1;

    /** @var int Sensor delay time frame (milliseconds) */
    private const DELAY_TIME = 500;

    /** @var int Min Symcon Object ID */
    private const IPS_MIN_ID = 10000;

    /** @var string Ident of the time frame timer */
    private const TIMER_IDENT = 'DelayTimer';

    /** @var string Diagnosis row color for errors (red) */
    private const ROW_ERROR = '#FFC0C0';

    /** @var string Diagnosis row color for blocking conditions (yellow) */
    private const ROW_BLOCKED = '#FFFFC0';

    /** @var int Diagnosis result color for errors (red) */
    private const RESULT_ERROR_COLOR = 0xFF0000;

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

        // Sensors ...
        $this->RegisterPropertyInteger('MotionVariable', 0);
        $this->RegisterPropertyInteger('LogicalLink', self::LINK_OR);
        $this->RegisterPropertyInteger('SensorVariable', 0);
        $this->RegisterPropertyInteger('SensorDelay', self::DELAY_TIME);
        $this->RegisterPropertyInteger('BrightnessVariable', 0);

        // Time Control ...
        $this->RegisterPropertyInteger('EventVariable', 0);

        // Devices ...
        $this->RegisterPropertyInteger('DeviceNumber', self::DEVICE_ONE);
        $this->RegisterPropertyInteger('SwitchVariable', 0);
        $this->RegisterPropertyString('SwitchVariables', '[]');

        // Advanced Settings ...
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyBoolean('ExecuteAlways', false);
        $this->RegisterPropertyBoolean('SettingsDirect', false);
        $this->RegisterPropertyBoolean('CheckLastTrigger', false);

        // Time of the last trigger (shown as variable only on demand)
        $this->RegisterAttributeInteger('LastTrigger', 0);

        // Up to v4: initial value of the threshold variable only (former form settings)
        $this->RegisterPropertyInteger('ThresholdValue', 0);
        $this->RegisterPropertyBoolean('ThresholdVariable', false);

        // Timer
        $this->RegisterTimer(self::TIMER_IDENT, 0, 'IPS_RequestAction(' . $this->InstanceID . ', "DelayTrigger", "");');

        // Wait for the kernel before accessing foreign variables
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    /**
     * Migrates the configuration of older versions.
     *
     * Up to v4 a single switch variable without an action was set directly.
     * Keep this behaviour for existing instances which rely on it.
     *
     * @param string $json Configuration data as JSON
     *
     * @return string Migrated configuration data as JSON
     */
    public function Migrate(string $json): string
    {
        //Never delete this line!
        parent::Migrate($json);

        $data = json_decode($json);
        if (!is_object($data) || !isset($data->configuration) || !is_object($data->configuration)) {
            return $json;
        }
        $config = $data->configuration;
        if (isset($config->SettingsDirect)) {
            return $json;
        }
        if ((int) ($config->DeviceNumber ?? self::DEVICE_ONE) !== self::DEVICE_ONE) {
            return $json;
        }
        $vid = (int) ($config->SwitchVariable ?? 0);
        if (($vid >= self::IPS_MIN_ID) && IPS_VariableExists($vid) && !HasAction($vid)) {
            $config->SettingsDirect = true;
            return json_encode($data);
        }
        return $json;
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

        // Maintain variables
        $created = (@$this->GetIDForIdent('Automatic') === false);
        $this->MaintainVariable('Automatic', $this->Translate('Automatic'), VARIABLETYPE_BOOLEAN, self::TPD_PRESENTATION_AUTOMATIC, 0, true);
        if ($created) {
            $this->SetValueBoolean('Automatic', true);
        }
        $this->EnableAction('Automatic');

        $brightness = ($this->ReadPropertyInteger('BrightnessVariable') >= self::IPS_MIN_ID);
        $created = (@$this->GetIDForIdent('BrightnessThreshold') === false);
        $this->MaintainVariable('BrightnessThreshold', $this->Translate('Brightness threshold'), VARIABLETYPE_INTEGER, self::TPD_PRESENTATION_THRESHOLD, 1, $brightness);
        if ($brightness) {
            if ($created) {
                $this->SetValueInteger('BrightnessThreshold', $this->ReadPropertyInteger('ThresholdValue'));
            }
            $this->EnableAction('BrightnessThreshold');
        }

        $last = $this->ReadPropertyBoolean('CheckLastTrigger');
        $created = (@$this->GetIDForIdent('LastTrigger') === false);
        $this->MaintainVariable('LastTrigger', $this->Translate('Last trigger'), VARIABLETYPE_INTEGER, self::TPD_PRESENTATION_TIME, 2, $last);
        if ($last && $created) {
            // Date/time presentation has no icon parameter, so set the object icon once on creation
            IPS_SetIcon($this->GetIDForIdent('LastTrigger'), 'clock-rotate-left');
            $this->SetValueInteger('LastTrigger', $this->ReadAttributeInteger('LastTrigger'));
        }

        // Reset a running time frame
        $this->ResetWindow();

        // Delete all variable registrations in order to readd them
        foreach ($this->GetMessageList() as $sender => $messages) {
            if (in_array(VM_UPDATE, $messages, true)) {
                $this->UnregisterMessage($sender, VM_UPDATE);
            }
        }

        // Foreign objects may not be available yet, ApplyChanges is called again on IPS_KERNELSTARTED
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $reference) {
            $this->UnregisterReference($reference);
        }

        // Register references
        $sensors = $this->GetMotionVariables();
        $variables = $this->GetSwitchVariables();
        $objects = array_merge($sensors, $variables, [
            $this->ReadPropertyInteger('BrightnessVariable'),
            $this->ReadPropertyInteger('EventVariable'),
            $this->ReadPropertyInteger('ScriptVariable'),
        ]);
        foreach ($objects as $object) {
            if (($object >= self::IPS_MIN_ID) && IPS_ObjectExists($object)) {
                $this->RegisterReference($object);
            }
        }

        // Check configuration
        $status = $this->CheckConfiguration($sensors, $variables);
        if ($status !== IS_ACTIVE) {
            $this->SetStatus($status);
            return;
        }

        // Register messages update = create our trigger
        foreach ($sensors as $sensor) {
            $this->RegisterMessage($sensor, VM_UPDATE);
        }

        $this->SetStatus(IS_ACTIVE);
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
        // Get form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $version = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        $this->ModifyFormElement($form['actions'], 'Version', function (array &$element) use ($version): void
        {
            $element['caption'] = $version;
        });

        // Number of devices
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        $this->ModifyFormElement($form['elements'], 'SwitchVariable', function (array &$element) use ($devices): void
        {
            $element['visible'] = ($devices === self::DEVICE_ONE);
        });

        // Device list (set status column)
        $values = [];
        $list = json_decode($this->ReadPropertyString('SwitchVariables'), true);
        foreach (is_array($list) ? $list : [] as $line) {
            $values[] = ['Status' => $this->GetVariableStatus((int) ($line['VariableID'] ?? 0))];
        }
        $this->ModifyFormElement($form['elements'], 'SwitchVariables', function (array &$element) use ($devices, $values): void
        {
            $element['visible'] = ($devices === self::DEVICE_MULTIPLE);
            $element['values'] = $values;
        });

        return json_encode($form);
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
            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
            case VM_UPDATE:
                if (!in_array($sender, $this->GetMotionVariables(), true)) {
                    $this->LogDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                    break;
                }
                if (!$data[1]) {
                    $this->LogDebug(__FUNCTION__, 'Motion #' . $sender . ' unchanged');
                    break;
                }
                if (!(bool) $data[0]) {
                    $this->LogDebug(__FUNCTION__, 'Motion #' . $sender . ' ended');
                    break;
                }
                $this->OnMotion($sender);
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
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $this->Stringify($value));
        switch ($ident) {
            case 'Automatic':
                $this->SetValueBoolean($ident, (bool) $value);
                if (!(bool) $value) {
                    $this->ResetWindow();
                }
                break;
            case 'BrightnessThreshold':
                $this->SetValueInteger($ident, (int) $value);
                break;
            case 'DelayTrigger':
                $this->OnWindowExpired();
                break;
            case 'OnCreateSchedule':
                $this->OnCreateSchedule();
                break;
            case 'OnDeviceNumber':
                $this->OnDeviceNumber((int) $value);
                break;
            case 'OnCheckState':
                $this->OnCheckState();
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'Unknown ident: ' . $ident);
        }
    }

    /**
     * Creates a weekly schedule below the instance.
     *
     * @return void
     */
    private function OnCreateSchedule(): void
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if (IPS_EventExists($eid)) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * User has selected a new number of devices.
     *
     * @param int $value Selected value.
     *
     * @return void
     */
    private function OnDeviceNumber(int $value): void
    {
        $this->UpdateFormField('SwitchVariable', 'visible', ($value === self::DEVICE_ONE));
        $this->UpdateFormField('SwitchVariables', 'visible', ($value === self::DEVICE_MULTIPLE));
    }

    /**
     * Diagnosis: shows all conditions and what a motion would do right now (read-only, switches nothing).
     *
     * @return void
     */
    private function OnCheckState(): void
    {
        $unused = $this->Translate('not used');
        $rows = [];

        // Configuration (errors are marked in the detail rows)
        $sensors = $this->GetMotionVariables();
        $variables = $this->GetSwitchVariables();
        $status = $this->CheckConfiguration($sensors, $variables);

        // Automatic
        $automatic = $this->IsAutomatic();
        $rows[] = $this->DiagnosisRow('Automatic', $automatic ? $this->Translate('ON') : $this->Translate('OFF'), $automatic ? '' : self::ROW_BLOCKED);

        // Motion detectors
        if (empty($sensors)) {
            $rows[] = $this->DiagnosisRow('Motion detector', $this->Translate('none selected'), self::ROW_ERROR);
        }
        foreach ($sensors as $mid) {
            if (!IPS_VariableExists($mid)) {
                $rows[] = $this->DiagnosisRow('Motion detector', '#' . $mid . ' ' . $this->Translate('Missing'), self::ROW_ERROR);
                continue;
            }
            $motion = (bool) GetValue($mid);
            $rows[] = $this->DiagnosisRow('Motion detector', IPS_GetName($mid) . ': ' . ($motion ? $this->Translate('motion detected') : $this->Translate('no motion')));
        }

        // Logical link and time frame
        if (count($sensors) < 2) {
            $rows[] = $this->DiagnosisRow('Link', $unused);
        } else {
            $link = $this->ReadPropertyInteger('LogicalLink');
            $delay = $this->ReadPropertyInteger('SensorDelay');
            $text = sprintf($this->Translate('%s, time frame: %d ms'), $this->Translate(self::LINK_NAMES[$link] ?? '?'), $delay);
            $rows[] = $this->DiagnosisRow('Link', $text, ($status === self::STATUS_DELAY_REQUIRED) ? self::ROW_ERROR : '');
            $waiting = (int) $this->GetBuffer('Trigger');
            if (($this->GetTimerInterval(self::TIMER_IDENT) > 0) && ($waiting > 0)) {
                $rows[] = $this->DiagnosisRow('Time frame', sprintf($this->Translate('running (started by #%d)'), $waiting));
            } else {
                $rows[] = $this->DiagnosisRow('Time frame', $this->Translate('not running'));
            }
        }

        // Weekly schedule
        $eid = $this->ReadPropertyInteger('EventVariable');
        if ($eid < self::IPS_MIN_ID) {
            $rows[] = $this->DiagnosisRow('Weekly schedule', $unused);
        } elseif (!IPS_EventExists($eid)) {
            $rows[] = $this->DiagnosisRow('Weekly schedule', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $info = $this->GetWeeklyScheduleInfo($eid);
            $color = '';
            if ($info['WeekPlanActiv'] !== 1) {
                $schedule = $this->Translate('disabled (ignored)');
            } elseif ($info['ActionID'] === self::SCHEDULE_OFF) {
                $schedule = $this->Translate('Inactive');
                $color = self::ROW_BLOCKED;
            } else {
                $schedule = $this->Translate('Active');
            }
            $rows[] = $this->DiagnosisRow('Weekly schedule', $schedule, $color);
        }

        // Brightness
        $bid = $this->ReadPropertyInteger('BrightnessVariable');
        $dark = $this->CheckBrightness();
        if ($bid < self::IPS_MIN_ID) {
            $rows[] = $this->DiagnosisRow('Brightness', $unused);
        } elseif (!IPS_VariableExists($bid)) {
            $rows[] = $this->DiagnosisRow('Brightness', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $threshold = $this->GetThreshold();
            $limit = ($threshold === 0) ? $this->Translate('always') : $threshold . ' lx';
            $rows[] = $this->DiagnosisRow('Brightness', sprintf($this->Translate('%s (threshold: %s)'), GetValueFormatted($bid), $limit), $dark ? '' : self::ROW_BLOCKED);
        }

        // Devices
        if (empty($variables)) {
            $rows[] = $this->DiagnosisRow('Device', $this->Translate('none selected'), ($this->ReadPropertyInteger('ScriptVariable') < self::IPS_MIN_ID) ? self::ROW_ERROR : '');
        }
        foreach ($variables as $vid) {
            if (!IPS_VariableExists($vid)) {
                $rows[] = $this->DiagnosisRow('Device', '#' . $vid . ' ' . $this->Translate('Missing'), self::ROW_ERROR);
                continue;
            }
            $state = $this->GetVariableStatus($vid);
            if ($state === 'OK') {
                $rows[] = $this->DiagnosisRow('Device', IPS_GetName($vid) . ': ' . (GetValueBoolean($vid) ? $this->Translate('ON') : $this->Translate('OFF')));
            } else {
                $rows[] = $this->DiagnosisRow('Device', IPS_GetName($vid) . ': ' . $state, self::ROW_ERROR);
            }
        }

        // Script
        $always = $this->ReadPropertyBoolean('ExecuteAlways');
        $sid = $this->ReadPropertyInteger('ScriptVariable');
        if ($sid < self::IPS_MIN_ID) {
            $rows[] = $this->DiagnosisRow('Script', $this->Translate('none'));
        } elseif (!IPS_ScriptExists($sid)) {
            $rows[] = $this->DiagnosisRow('Script', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $rows[] = $this->DiagnosisRow('Script', sprintf($this->Translate($always ? '%s (always, regardless of brightness)' : '%s (together with the devices)'), IPS_GetName($sid)));
        }

        // Last trigger
        $last = $this->ReadAttributeInteger('LastTrigger');
        $rows[] = $this->DiagnosisRow('Last trigger', ($last > 0) ? date('d.m.Y H:i:s', $last) : $this->Translate('never'));

        // Result
        $error = false;
        $reason = $this->GetBlockReason();
        if ($status !== IS_ACTIVE) {
            $result = $this->Translate('The presence detector does not work, see the marked entries!');
            $error = true;
        } elseif ($reason !== '') {
            $result = sprintf($this->Translate('Motion does not switch: %s'), $this->Translate($reason));
        } elseif (!$dark) {
            $result = $this->Translate(($always && ($sid >= self::IPS_MIN_ID)) ? 'Too bright, motion only executes the script.' : 'Too bright, motion does not switch.');
        } elseif (empty($variables)) {
            $result = $this->Translate('Motion executes the script.');
        } else {
            $result = $this->Translate('Motion switches on the devices.');
        }

        $this->UpdateFormField('DiagnosisList', 'rowCount', count($rows));
        $this->UpdateFormField('DiagnosisList', 'values', json_encode($rows));
        $this->UpdateFormField('DiagnosisResult', 'caption', $this->Translate('Result') . ': ' . $result);
        $this->UpdateFormField('DiagnosisResult', 'color', $error ? self::RESULT_ERROR_COLOR : 0);
        $this->UpdateFormField('DiagnosisPopup', 'visible', true);
    }

    /**
     * Builds a row of the diagnosis list.
     *
     * @param string $check Name of the check (untranslated)
     * @param string $state State (already translated)
     * @param string $color Row color (empty for transparent)
     *
     * @return array<string,string> List row
     */
    private function DiagnosisRow(string $check, string $state, string $color = ''): array
    {
        return ['Check' => $this->Translate($check), 'State' => $state, 'rowColor' => $color];
    }

    /**
     * A motion detector has detected a motion, evaluate the logical link.
     *
     * @param int $sender Variable ID of the motion detector.
     *
     * @return void
     */
    private function OnMotion(int $sender): void
    {
        $sensors = $this->GetMotionVariables();
        if (count($sensors) < 2) {
            $this->LogDebug(__FUNCTION__, 'Single motion detector #' . $sender);
            $this->Fire();
            return;
        }
        $waiting = (int) $this->GetBuffer('Trigger');
        $running = ($this->GetTimerInterval(self::TIMER_IDENT) > 0);
        switch ($this->ReadPropertyInteger('LogicalLink')) {
            case self::LINK_OR:
                // Fire and block further triggers within the time frame
                if ($running) {
                    $this->LogDebug(__FUNCTION__, 'OR: blocked within time frame (#' . $sender . ')');
                    return;
                }
                $this->StartWindow($sender);
                $this->Fire();
                break;
            case self::LINK_AND:
                // Fire when the other detector follows within the time frame
                if (!$running) {
                    $this->LogDebug(__FUNCTION__, 'AND: wait for second detector (#' . $sender . ')');
                    $this->StartWindow($sender);
                } elseif ($waiting === $sender) {
                    $this->LogDebug(__FUNCTION__, 'AND: same detector again, keep waiting (#' . $sender . ')');
                } else {
                    $this->LogDebug(__FUNCTION__, 'AND: condition fulfilled (#' . $waiting . ', #' . $sender . ')');
                    $this->ResetWindow();
                    $this->Fire();
                }
                break;
            case self::LINK_NOT:
                // Fire when the first detector is not followed by the second within the time frame
                if ($sender === $this->ReadPropertyInteger('MotionVariable')) {
                    if (!$running) {
                        $this->LogDebug(__FUNCTION__, 'NOT: wait for time frame (#' . $sender . ')');
                        $this->StartWindow($sender);
                    }
                } elseif ($running) {
                    $this->LogDebug(__FUNCTION__, 'NOT: condition violated by #' . $sender);
                    $this->ResetWindow();
                } else {
                    $this->LogDebug(__FUNCTION__, 'NOT: second detector alone ignored (#' . $sender . ')');
                }
                break;
        }
    }

    /**
     * The time frame of the logical link has expired.
     *
     * @return void
     */
    private function OnWindowExpired(): void
    {
        $waiting = (int) $this->GetBuffer('Trigger');
        $this->ResetWindow();
        $fire = ($this->ReadPropertyInteger('LogicalLink') === self::LINK_NOT) && ($waiting > 0) && (count($this->GetMotionVariables()) === 2);
        $this->LogDebug(__FUNCTION__, 'Time frame expired, fire: ' . ($fire ? 'true' : 'false'));
        if ($fire) {
            $this->Fire();
        }
    }

    /**
     * Starts the time frame of the logical link.
     *
     * @param int $sender Motion detector which started the time frame.
     *
     * @return void
     */
    private function StartWindow(int $sender): void
    {
        $delay = $this->ReadPropertyInteger('SensorDelay');
        if ($delay <= 0) {
            return;
        }
        $this->SetBuffer('Trigger', (string) $sender);
        $this->SetTimerInterval(self::TIMER_IDENT, $delay);
    }

    /**
     * Stops the time frame of the logical link.
     *
     * @return void
     */
    private function ResetWindow(): void
    {
        $this->SetTimerInterval(self::TIMER_IDENT, 0);
        $this->SetBuffer('Trigger', '0');
    }

    /**
     * Motion condition is fulfilled, check the secondary conditions and switch.
     *
     * @return void
     */
    private function Fire(): void
    {
        $reason = $this->GetBlockReason();
        if ($reason !== '') {
            $this->LogDebug(__FUNCTION__, $reason);
            return;
        }
        $dark = $this->CheckBrightness();
        $executed = false;
        if ($dark) {
            $executed = $this->SwitchDevices();
        }
        if (($dark || $this->ReadPropertyBoolean('ExecuteAlways')) && $this->ExecuteScript()) {
            $executed = true;
        }
        if ($executed) {
            $this->UpdateLastTrigger(time());
        }
    }

    /**
     * Remembers the time of the last trigger (attribute) and updates the variable (if created).
     *
     * @param int $timestamp Time of the trigger
     *
     * @return void
     */
    private function UpdateLastTrigger(int $timestamp): void
    {
        $this->WriteAttributeInteger('LastTrigger', $timestamp);
        if ($this->ReadPropertyBoolean('CheckLastTrigger') && (@$this->GetIDForIdent('LastTrigger') !== false)) {
            $this->SetValueInteger('LastTrigger', $timestamp);
        }
    }

    /**
     * Returns the reason why the presence detector is blocked.
     *
     * @return string Untranslated reason or empty string if not blocked.
     */
    private function GetBlockReason(): string
    {
        if (!$this->IsAutomatic()) {
            return 'Automatic is switched off!';
        }
        $eid = $this->ReadPropertyInteger('EventVariable');
        if (($eid >= self::IPS_MIN_ID) && IPS_EventExists($eid)) {
            $state = $this->GetWeeklyScheduleInfo($eid);
            if (($state['WeekPlanActiv'] === 1) && ($state['ActionID'] === self::SCHEDULE_OFF)) {
                return 'Weekly schedule is inactive!';
            }
        }
        return '';
    }

    /**
     * Checks whether the automatic is switched on.
     *
     * @return bool True if the automatic is on (or the variable does not exist).
     */
    private function IsAutomatic(): bool
    {
        return (@$this->GetIDForIdent('Automatic') === false) || (bool) $this->GetValue('Automatic');
    }

    /**
     * Checks the brightness condition.
     *
     * @return bool True if it is dark enough (or no brightness is used).
     */
    private function CheckBrightness(): bool
    {
        $bid = $this->ReadPropertyInteger('BrightnessVariable');
        if (($bid < self::IPS_MIN_ID) || !IPS_VariableExists($bid)) {
            return true;
        }
        $threshold = $this->GetThreshold();
        $brightness = GetValue($bid);
        if (($threshold !== 0) && ($brightness > $threshold)) {
            $this->LogDebug(__FUNCTION__, 'Brightness ' . $brightness . ' above threshold ' . $threshold);
            return false;
        }
        return true;
    }

    /**
     * Returns the current brightness threshold (0 = always).
     *
     * @return int Threshold in lux
     */
    private function GetThreshold(): int
    {
        if (@$this->GetIDForIdent('BrightnessThreshold') !== false) {
            return (int) $this->GetValue('BrightnessThreshold');
        }
        return $this->ReadPropertyInteger('ThresholdValue');
    }

    /**
     * Switches on all configured devices.
     *
     * @return bool True if at least one device was switched on.
     */
    private function SwitchDevices(): bool
    {
        $switched = false;
        foreach ($this->GetSwitchVariables() as $vid) {
            if ($this->SwitchOn($vid)) {
                $switched = true;
            }
        }
        return $switched;
    }

    /**
     * Switches on a single device.
     *
     * @param int $vid Variable ID of the device.
     *
     * @return bool True if the device was switched on.
     */
    private function SwitchOn(int $vid): bool
    {
        if (!IPS_VariableExists($vid)) {
            $ok = false;
        } elseif (HasAction($vid)) {
            $ok = (@RequestAction($vid, true) !== false);
        } elseif ($this->IsDirectSwitchable($vid)) {
            $ok = @SetValueBoolean($vid, true);
        } else {
            $ok = false;
        }
        if ($ok) {
            $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' switched ON');
        } else {
            $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' could not be switched!');
            $this->LogMessage(sprintf($this->Translate('Device #%d could not be switched on!'), $vid), KL_WARNING);
        }
        return $ok;
    }

    /**
     * Executes the script if deposited.
     *
     * @return bool True if the script was started.
     */
    private function ExecuteScript(): bool
    {
        $sid = $this->ReadPropertyInteger('ScriptVariable');
        if ($sid < self::IPS_MIN_ID) {
            return false;
        }
        if (!IPS_ScriptExists($sid)) {
            $this->LogDebug(__FUNCTION__, 'Script #' . $sid . ' does not exist!');
            return false;
        }
        $sensors = $this->GetMotionVariables();
        $variables = $this->GetSwitchVariables();
        $ret = IPS_RunScriptEx($sid, [
            'MotionVariable'     => (count($sensors) === 1) ? $sensors[0] : implode(',', $sensors),
            'BrightnessVariable' => $this->ReadPropertyInteger('BrightnessVariable'),
            'SwitchVariable'     => (count($variables) === 1) ? $variables[0] : implode(',', $variables),
            'ThresholdValue'     => $this->GetThreshold(),
            'INSTANCE'           => $this->InstanceID,
        ]);
        $this->LogDebug(__FUNCTION__, 'Script return value: ' . $this->Stringify($ret));
        return true;
    }

    /**
     * Returns the configured motion detectors (first, second).
     *
     * @return array<int> Variable IDs
     */
    private function GetMotionVariables(): array
    {
        $sensors = [];
        foreach (['MotionVariable', 'SensorVariable'] as $property) {
            $variable = $this->ReadPropertyInteger($property);
            if ($variable >= self::IPS_MIN_ID) {
                $sensors[] = $variable;
            }
        }
        return array_values(array_unique($sensors));
    }

    /**
     * Returns the configured switch variables (single or multiple devices).
     *
     * @return array<int> Variable IDs
     */
    private function GetSwitchVariables(): array
    {
        if ($this->ReadPropertyInteger('DeviceNumber') === self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('SwitchVariable');
            return ($variable >= self::IPS_MIN_ID) ? [$variable] : [];
        }
        $variables = [];
        $list = json_decode($this->ReadPropertyString('SwitchVariables'), true);
        foreach (is_array($list) ? $list : [] as $line) {
            $variable = (int) ($line['VariableID'] ?? 0);
            if ($variable >= self::IPS_MIN_ID) {
                $variables[] = $variable;
            }
        }
        return array_values(array_unique($variables));
    }

    /**
     * Checks the configuration and returns the resulting instance status.
     *
     * @param array<int> $sensors   Configured motion detectors.
     * @param array<int> $variables Configured switch variables.
     *
     * @return int Status code
     */
    private function CheckConfiguration(array $sensors, array $variables): int
    {
        if (empty($sensors)) {
            $this->LogDebug(__FUNCTION__, 'No motion variable configured!');
            return IS_INACTIVE;
        }
        foreach ($sensors as $mid) {
            if (!IPS_VariableExists($mid)) {
                $this->LogDebug(__FUNCTION__, 'Motion variable #' . $mid . ' does not exist!');
                return self::STATUS_MOTION_MISSING;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (empty($variables) && ($script < self::IPS_MIN_ID)) {
            $this->LogDebug(__FUNCTION__, 'Neither switch variable nor script configured!');
            return IS_INACTIVE;
        }
        foreach ($variables as $vid) {
            if (!IPS_VariableExists($vid)) {
                $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' does not exist!');
                return self::STATUS_VARIABLE_MISSING;
            }
            if ($this->GetVariableStatus($vid) !== 'OK') {
                $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' is not switchable!');
                return self::STATUS_VARIABLE_INVALID;
            }
        }
        $brightness = $this->ReadPropertyInteger('BrightnessVariable');
        if (($brightness >= self::IPS_MIN_ID) && !IPS_VariableExists($brightness)) {
            $this->LogDebug(__FUNCTION__, 'Brightness variable #' . $brightness . ' does not exist!');
            return self::STATUS_BRIGHTNESS_MISSING;
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (($event >= self::IPS_MIN_ID) && !IPS_EventExists($event)) {
            $this->LogDebug(__FUNCTION__, 'Event #' . $event . ' does not exist!');
            return self::STATUS_SCHEDULE_MISSING;
        }
        if (($script >= self::IPS_MIN_ID) && !IPS_ScriptExists($script)) {
            $this->LogDebug(__FUNCTION__, 'Script #' . $script . ' does not exist!');
            return self::STATUS_SCRIPT_MISSING;
        }
        if ((count($sensors) === 2) && ($this->ReadPropertyInteger('LogicalLink') !== self::LINK_OR) && ($this->ReadPropertyInteger('SensorDelay') <= 0)) {
            $this->LogDebug(__FUNCTION__, 'AND/NOT link requires a time frame!');
            return self::STATUS_DELAY_REQUIRED;
        }
        return IS_ACTIVE;
    }

    /**
     * Checks whether a variable without action may be set directly.
     * Only allowed by the user and only for variables which do not belong to an instance.
     *
     * @param int $vid Variable ID
     *
     * @return bool True if the value may be set directly
     */
    private function IsDirectSwitchable(int $vid): bool
    {
        return $this->ReadPropertyBoolean('SettingsDirect') && !IPS_InstanceExists(IPS_GetParent($vid));
    }

    /**
     * Returns the status of a given switch variable.
     *
     * @param int $vid Variable ID.
     *
     * @return string 'OK' or a translated status message
     */
    private function GetVariableStatus(int $vid): string
    {
        if (!IPS_VariableExists($vid)) {
            return $this->Translate('Missing');
        }
        if (IPS_GetVariable($vid)['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            return $this->Translate('Bool required');
        }
        if (!HasAction($vid) && !$this->IsDirectSwitchable($vid)) {
            return $this->Translate('Action required');
        }
        return 'OK';
    }
}
