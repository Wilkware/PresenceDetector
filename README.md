# Präsenzmelder (Presence Detector)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/praesenzmelder/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-4.0.20240908-orange.svg?style=flat-square)](https://github.com/Wilkware/PresenceDetector)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/PresenceDetector/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/PresenceDetector/actions)

Das Modul Präsenzmelder (Presence Detector) schaltet in Abhängigkeit von Bewegung(en) und Helligkeit ein oder mehrere Geräte ein bzw. führt ein Skript aus. Die Bewegungsdaten können dabei logisch verknüpft werden. Zusätzlich kann ein Schwellwert für die Helligkeit hinterlegt werden.  

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

* Einfaches oder logisches Verknüpfen von Bewegungsdaten (ein oder zwei Bewegungsmelder).
* Einstellung eines Helligkeitswertes, ab welchem weiterverarbeitet werden soll.
* Hinterlegung eines Wochenplans zum gezielten Aktivieren bzw. Deaktivierung des Melders
* Ein oder mehrere Geräte einschalten
* Zusätzlich bzw. ausschließlich kann ein Skript ausgeführt werden.
* Der Helligkeitsschwellwert kann bei Bedarf auch über das Webfront gesetzt werden.

### 2. Voraussetzungen

* Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul _Präsenzmelder_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/PresenceDetector` oder `git://github.com/Wilkware/PresenceDetector.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Präsenzmelder_-Modul (Alias: _Bewegungsmelder_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

> Sensoren ...

Name                 | Beschreibung
-------------------- | ---------------------------------
1' Bewegungsvariable | Statusvariable des primären Bewegungsmelders (true = Anwesend; false = Abwesend).
Verknüpfung          | Logische Verknüpfung der Bewegungsdaten (UND, ODER, NICHT).
2' Bewegungsvariable | Statusvariable des sekundären Bewegungsmelders (true = Anwesend; false = Abwesend).
Helligkeitsvariable  | Quellvariable, über welche die Helligkeit abgefragt werden kann, bei HmIP-SMI ist es ILLUMINATION.
Schwellwert          | Schwellwert, von Immer(0) bis 500 Lux.

> Zeitsteuerung ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Zeitplan             | Wochenprogram, welches den Bewegungsmelder zeitgesteuert aktiviert bzw. deaktiviert.
ZEITPLAN HINZUFÜGEN  | Button zum Erzeugen und Hinzufügen eines Wochenprogrammes.

> Geräte ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Geräteauswahl        | 'Ein Gerät' oder 'Mehrere Geräte' - Umschalten zwischen Variablenauswahl und Variablenliste.
Schaltvariable*      | Zielvariable, die bei hinreichender Bedingung geschalten wird (true). *[Ein Gerät]
Schaltvariablen*     | Zielvariablen, welche alle bei hinreichender Bedingung geschalten werden (true). *[Mehrere Geräte]
Skript               | Skript(auswahl), welches gleichzeitig ausgeführt werden soll.

> Erweiterte Einstellungen ...

Name                 | Beschreibung
---------------------| ---------------------------------
Checkbox Schwellwert | Schalter, ob eine Zustandsvariable für den Helligkeitsschwellwert angelegt werden soll.

Einem hinterlegtem Skript werden folgende Konfigurationswerte mitgegeben:  
  
Parameter            | Beschreibung
-------------------- | ---------------------------------
MotionVariable       | ID(s) der Bewegungsvariable(n), Zugriff im Skript via _$_IPS['MotionVariable']_
BrightnessVariable   | ID der Helligkeitsvariable, Zugriff im Skript via _$_IPS['BrightnessVariable']_
SwitchVariable       | ID(s) der Schaltvariable(s), Zugriff im Skript via _$_IPS['SwitchVariable']_
ThresholdValue       | Wert von Schwellwert, Zugriff im Skript via _$_IPS['ThresholdValue']_

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                   | Typ     | Beschreibung
---------------------- | ------- | ------------------------------
Helligkeitsschwellwert | Integer | Helligkeitswert ab welchem geschalten werden soll

_Hinweis:_ Die Variable 'Helligkeitsschwellwert' wird nur angelegt, wenn dies unter 'Erweiterte Einstellungen' aktiviert ist.

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable               | Darstellung   | Werte
---------------------- | ------------- | ------------------------------
Helligkeitsschwellwert | Schieberegler | 0 – 500 lx

### 7. Visualisierung

Die erzeugten Variablen können direkt in die Visualisierung verlinkt werden.  

### 8. Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 9. Versionshistorie

v4.0.20240908

* _NEU_: Kompatibilität auf IPS 6.4 hoch gesetzt
* _FIX_: Bibliotheks- bzw. Modulinfos vereinheitlicht
* _FIX_: Namensnennung und Repo vereinheitlicht
* _FIX_: Update Style-Checks
* _FIX_: Übersetzungen überarbeitet und verbessert
* _FIX_: Dokumentation vereinheitlicht

v3.1.20220909

* _NEU_: Zeitfenster wird jetzt bei allen Verknüpfungen berücksichtigt
* _NEU_: Zeitfenster jetzt bis 2 Min einstellbar
* _FIX_: Doppelte Ausführung bei OR gefixt
* _FIX_: Bibliotheken nachgezogen

v3.0.20220320

* _NEU_: Zweiten Bewegungsmelder hinzugefügt
* _NEU_: Logische Verknüpfung der Bewegungsmelder eingeführt
* _NEU_: Kompatibilität auf IPS 6.0 hoch gesetzt
* _NEU_: Bibliotheks- bzw. Modulinfos vereinheitlicht
* _NEU_: Konfigurationsdialog überarbeitet (v6 Möglichkeiten genutzt)
* _NEU_: Auswahl des Helligkeitsschwellwertes erweitert
* _NEU_: Konfiguration der Zeitsteuerung überarbeitet
* _NEU_: Umschalten zwischen einem oder mehreren schaltbaren Geräten
* _NEU_: Eine reine boolesche Schaltvariable (ein Gerät) wird automatisch erkannt
* _NEU_: Referenzieren der Gerätevariablen hinzugefügt (sicheres Löschen)
* _FIX_: Funktion `TPD_SwitchState` wegen neuer Prozessverarbeitung entfernt
* _FIX_: Interne Bibliotheken erweitert und vereinheitlicht
* _FIX_: Markdown der Dokumentation überarbeitet

v2.1.20210723

* _NEU_: Konfigurationsformular überarbeitet und vereinheitlicht
* _NEU_: Schwellwert(Helligkeit) kann über Webfront(RequestAction) gesetzt bzw. manipuliert werden
* _FIX_: Funktion `TPD_Threshold` wegen Verwendung von IPS_SetProperty entfernt
* _FIX_: Übersetzungen nachgezogen
* _FIX_: Interne Bibliotheken überarbeitet und vereinheitlicht
* _FIX_: Debug Meldungen überarbeitet
* _FIX_: Dokumentation überarbeitet

v2.0.20200422

* _NEU_: Zeitplan hinzugefügt
* _NEU_: Unterstützung für die Erstellung eines Wochenplans
* _FIX_: Interne Bibliotheken überarbeitet
* _FIX_: Dokumentation überarbeitet

v1.3.20191105

* _NEU_: Skriptaufruf auf IPS_RunScriptEx umgestellt (Variablenübergabe)
* _FIX_: Debugausgabe war fehlerhaft bei hinterlegtem Skript
* _FIX_: Dokumentation überarbeitet

v1.2.20190818

* _NEU_: Umstellung für Module Store
* _FIX_: Dokumentation überarbeitet

v1.1.20170322

* _FIX_: Anpassungen für IPS Version 5

v1.0.20170125

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
