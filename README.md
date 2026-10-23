# 🏃 Präsenzmelder (Presence Detector)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/praesenzmelder/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-5.0.20261023-orange.svg?style=flat-square)](https://github.com/Wilkware/PresenceDetector)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/PresenceDetector/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/PresenceDetector/actions)

Das Modul Präsenzmelder (Presence Detector) schaltet in Abhängigkeit von Bewegung(en) und Helligkeit ein oder mehrere Geräte ein bzw. führt ein Skript aus. Die Bewegungsdaten können dabei logisch verknüpft werden. Zusätzlich kann ein Schwellwert für die Helligkeit hinterlegt werden.

## Inhaltsverzeichnis

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

* Auswertung von einem oder zwei Bewegungsmeldern, bei zwei Meldern logisch verknüpft (UND, ODER, NICHT) innerhalb eines Zeitfensters.
* Einschalten eines oder mehrerer Geräte (boolesche Schaltvariablen) bei erkannter Bewegung.
* Berücksichtigung der Helligkeit über einen Schwellwert, der als Statusvariable (z. B. über die Visualisierung) eingestellt wird.
* Hinterlegung eines Wochenplans zum gezielten Aktivieren bzw. Deaktivieren des Präsenzmelders.
* Ein- und Ausschalten der Automatik über eine schaltbare Statusvariable.
* Optionale Anzeige des Zeitpunktes der letzten Auslösung als Statusvariable.
* Zusätzlich bzw. ausschließlich kann ein Skript ausgeführt werden, wahlweise auch unabhängig von der Helligkeit.
* Diagnose über das Konfigurationsformular: zeigt alle Bedingungen und was eine Bewegung gerade auslösen würde bzw. warum nicht.

### 2. Voraussetzungen

* Symcon ab Version 8.1

### 3. Installation

* Über den Modul Store das Modul _Präsenzmelder_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/PresenceDetector` oder `git://github.com/Wilkware/PresenceDetector.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Präsenzmelder_-Modul (Alias: _Bewegungsmelder_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> 🏃 Sensoren ...

Name                 | Beschreibung
-------------------- | ---------------------------------
1. Bewegungsvariable | Boolesche Statusvariable des primären Bewegungsmelders (true = Bewegung; false = keine Bewegung).
Verknüpfung          | Logische Verknüpfung der beiden Bewegungsmelder (UND, ODER, NICHT); nur relevant, wenn zwei Bewegungsmelder ausgewählt sind.
2. Bewegungsvariable | Boolesche Statusvariable des sekundären Bewegungsmelders (true = Bewegung; false = keine Bewegung).
Zeitfenster          | Zeitfenster in Millisekunden für die Verknüpfung (UND und NICHT benötigen einen Wert größer 0).
Helligkeitsvariable  | Quellvariable, über welche die Helligkeit abgefragt werden kann, bei HmIP-SMI ist es ILLUMINATION.

Die Verknüpfungen im Detail:

Verknüpfung | Verhalten
----------- | ---------------------------------
∧ (und)     | Beide Bewegungsmelder müssen innerhalb des Zeitfensters (in beliebiger Reihenfolge) auslösen.
∨ (oder)    | Jeder Bewegungsmelder löst aus; weitere Auslösungen innerhalb des Zeitfensters werden ignoriert.
¬ (nicht)   | Der 1. Bewegungsmelder löst aus, wenn der 2. nicht innerhalb des Zeitfensters folgt (z. B. Richtungserkennung).

> ⏱️ Zeitsteuerung ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Zeitplan             | Wochenplan, welcher den Präsenzmelder zeitgesteuert aktiviert bzw. deaktiviert.
ZEITPLAN HINZUFÜGEN  | Button zum Erzeugen und Hinzufügen eines Wochenplans.

> 💡 Geräte ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Geräteanzahl         | 'Ein Gerät' oder 'Mehrere Geräte' - Umschalten zwischen Variablenauswahl und Variablenliste.
Schaltvariable*      | Boolesche Zielvariable, die bei erfüllter Bedingung eingeschaltet wird (true).
Schaltvariablen*     | Boolesche Zielvariablen, welche alle bei erfüllter Bedingung eingeschaltet werden (true). Die Spalte 'Zustand' zeigt, ob die Variable schaltbar ist.

(*) abhängig von Auswahl der Geräteanzahl

> ⚙️ Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Gleichzeitiges Ausführen eines Skriptes          | Auswahl eines Skriptes, welches zusätzlich (bzw. ohne Schaltvariablen ausschließlich) ausgeführt werden soll.
Skript immer ausführen                           | Das Skript wird auch ausgeführt, wenn die Helligkeit über dem Schwellwert liegt. Automatik und Wochenplan gelten weiterhin.
Schaltvariablen ohne Aktion direkt setzen        | Schaltvariablen ohne Aktion werden direkt gesetzt (SetValue); nur für Variablen, die zu keiner Instanz gehören.
Variable für Anzeige der letzten Auslösung anlegen | Schalter, ob eine Statusvariable mit dem Zeitpunkt der letzten Auslösung angelegt werden soll (intern wird er immer geführt).

Einem hinterlegten Skript werden folgende Werte mitgegeben:

Parameter            | Beschreibung
-------------------- | ---------------------------------
MotionVariable       | ID(s) der Bewegungsvariable(n), Zugriff im Skript via `$_IPS['MotionVariable']`
BrightnessVariable   | ID der Helligkeitsvariable, Zugriff im Skript via `$_IPS['BrightnessVariable']`
SwitchVariable       | ID(s) der Schaltvariable(n), Zugriff im Skript via `$_IPS['SwitchVariable']`
ThresholdValue       | Aktueller Helligkeitsschwellwert, Zugriff im Skript via `$_IPS['ThresholdValue']`
INSTANCE             | ID der Präsenzmelder-Instanz, Zugriff im Skript via `$_IPS['INSTANCE']`

__Hinweis:__ Bestehende Instanzen, die bisher eine Schaltvariable ohne Aktion verwendet haben, werden beim Update automatisch auf 'Schaltvariablen ohne Aktion direkt setzen' umgestellt. Der bisher in der Konfiguration hinterlegte Schwellwert wird beim Anlegen der Variable 'Helligkeitsschwellwert' als Startwert übernommen.

Aktionsbereich:

> 🧪 Test ...

Aktion          | Beschreibung
--------------- | ---------------------------------
ZUSTAND PRÜFEN  | Zeigt alle Bedingungen auf einen Blick (Automatik, Bewegungsmelder, Verknüpfung, Zeitfenster, Wochenplan, Helligkeit, Geräte, Skript, letzte Auslösung) und als Ergebnis, was eine Bewegung gerade auslösen würde bzw. warum nicht. Fehler werden rot, Bedingungen, die das Schalten verhindern (z. B. Automatik aus, inaktiver Wochenplan oder zu hell), gelb hinterlegt. Es wird dabei nichts geschaltet.

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Ident               | Name                   | Typ     | Beschreibung
------------------- | ---------------------- | ------- | ------------------------------
Automatic           | Automatik              | Boolean | Ein- und Ausschalten des Präsenzmelders (z. B. bei Besuch oder Partys)
BrightnessThreshold | Helligkeitsschwellwert | Integer | Helligkeitswert, bis zu dem geschaltet wird (0 = immer)
LastTrigger         | Letzte Auslösung       | Integer | Zeitpunkt, zu dem zuletzt Geräte geschaltet bzw. das Skript ausgeführt wurde

_Hinweis:_ Die Variable 'Helligkeitsschwellwert' wird nur angelegt, wenn eine Helligkeitsvariable ausgewählt ist, die Variable 'Letzte Auslösung' nur, wenn dies unter 'Erweiterte Einstellungen' aktiviert ist. Zusätzlich kann über den Button 'Zeitplan hinzufügen' ein Wochenplan (Ident `circuit_diagram`) angelegt werden.

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable               | Darstellung    | Werte
---------------------- | -------------- | ------------------------------
Automatik              | Schalter       | An / Aus
Helligkeitsschwellwert | Schieberegler  | 0 – 500 lx (Schrittweite 1)
Letzte Auslösung       | Datum/Uhrzeit  | Datum und Uhrzeit (Stunden, Minuten, Sekunden)

### 7. Visualisierung

Es ist keine eigene Kachel integriert, die Instanz wird über die Standard-Kachel mit ihren Statusvariablen dargestellt.  
Die Automatik kann über die Statusvariable "Automatik", der Schwellwert über die Statusvariable "Helligkeitsschwellwert" gesteuert werden.  
Die Statusvariable "Letzte Auslösung" zeigt an, wann der Präsenzmelder zuletzt geschaltet hat.

### 8. Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 9. Versionshistorie

v5.0.20261023

* _NEU_: Kompatibilität auf Symcon 8.1 angehoben, Code und interne Bibliotheken modernisiert
* _NEU_: Konfigurationsformular überarbeitet und vereinheitlicht (inkl. Versionsanzeige und Statuscodes)
* _NEU_: Statusvariable 'Automatik' zum Ein- und Ausschalten des Präsenzmelders
* _NEU_: Optionale Statusvariable 'Letzte Auslösung'
* _NEU_: Helligkeitsschwellwert immer als Statusvariable (bei ausgewählter Helligkeitsvariable), Konfigurationswert wird als Startwert übernommen
* _NEU_: Option 'Schaltvariablen ohne Aktion direkt setzen' (nur Variablen, die zu keiner Instanz gehören), bestehende Instanzen werden automatisch migriert
* _NEU_: Aktionsbereich mit 'Zustand prüfen' zur Diagnose aller Bedingungen
* _NEU_: Ausgeführtes Skript erhält zusätzlich die Instanz
* _FIX_: 'Skript immer ausführen' führte das Skript auch bei nicht erfüllter Verknüpfung, bei Ablauf des Zeitfensters und bei inaktivem Wochenplan aus
* _FIX_: Bei UND bzw. NICHT brach eine wiederholte Auslösung desselben Bewegungsmelders das Zeitfenster ab
* _FIX_: Bei ODER hob eine weitere Auslösung innerhalb des Zeitfensters die Sperre auf
* _FIX_: Bei mehreren Geräten wurde ein Schaltfehler nur für das letzte Gerät gemeldet
* _FIX_: Skript erhielt ohne zweiten Bewegungsmelder eine falsche Bewegungsvariable ("ID,1")
* _FIX_: Schaltvariablen mit Darstellung statt Profil werden akzeptiert
* _FIX_: Fehlende Variablen, Wochenpläne und Skripte abgesichert, Start erst nach vollständig geladenem Kernel
* _FIX_: Übersetzungen und Dokumentation überarbeitet

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
