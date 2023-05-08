# Wolf ISM8


### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Auslesen diverser Variablen wie Wasservolumen, Ventilstatus, Wasserdruck u.v.a.m, schreiben von Daten wie bspw. Programmwahl Heizkreis, Sollwertkorrektur oder 1x Warmwasserladung.

### 2. Voraussetzungen

- IP-Symcon ab Version 6.3

### 3. Software-Installation

* Über den Module Store das 'Wolf ISM8'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/lorbetzki/net.lorbetzki.wolfism8.git

### 4. Einrichten der Instanzen in IP-Symcon

 Vorab muss die WebUI des ISM8-Gerätes aufgerufen werden, unter Netzwerk -> Kommunikationspartner muss bei Zielserver-IP: die IP Adresse eures Symcon Servers eingetragen werden, den Zielserver-Port: könnt Ihr auf 12004 belassen. Möchtet Ihr den ändern, merkt euch den Port.

 Dann gehen wir zu Symcon über:
 Unter 'Instanz hinzufügen' kann das 'Wolf ISM8'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

 Es wird nun eine Serverinstanz erstellt, als Port wird der Standardport 12004 eingetragen oder falls ihr den geändert habt, den gemerkten.

 Das war es soweit, Ihr müsst nun eure Heizung aus- und wieder einschalten, damit das ISM8 die Änderungen des Zielservers übernimmt. Nun kommen die Daten an.

__Konfigurationsseite__:

Die zahlen in der Klammern entsprechen den Datenpunkt-IDS welches Ihr auch aus der Anleitung des ISM8 entnehmen könnt.

Schreibbare Datenpunkte aktivieren.

Name          				     | Beschreibung
-------------------------------- | ----------------------------------------------------
Programmwahl Heizkreis (57)      | Programmwahl Heizkreis, bspw Auto, Standby, Comfort Heizbetrieb usw.
Sollwertkorrektur (65)           | Anpassung der Sollwertkorrektur 
1x Warmwasserladung Global (194) | veranlasst die Anlage das Warmwasser einmalig auf Höchsttemperatur zu bringen.

Optional: Störungsmeldungen entfernen. Nach einem Neustart der Heizung werden div. Störmeldungen gesendet, von Datenpunkten die die Anlage womöglich gar nicht hat. Hier kann man diese Meldungen entfernen so dass keine Variable angelegt wird.

Name          				     | 
-------------------------------- | 
Heizgerät 1 (1)                  |
Heizgerät 2 (14)                 |
Heizgerät 3 (27)                 |
Heizgerät 4 (40)                 |
Systembedienmodul (53)           | 
Kaskadenmodul (106)              |
Mischermodul 1 (114)             |
Mischermodul 2 (121)             |
Mischermodul 3 (128)             |
Solarmodul (135)                 |
CWL Excellent / CWL 2 (148)      |


### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Je nach Art und Umfang der Anlage werden div. Statusvariablen angelegt, die Beschreibung entnehmen Sie daher aus der Anleitung der ISM8. 
Die Statusvariablen werden im nach folgendem Schema angelegt

DatenpunktID_Name des Datenpunktes
z.B. 1_Heizkreis oder 65_Sollwertkorrketur. 

#### Profile

Name                    | Typ
------------------------| -------
_ISM8_DPT_Switch              | bool
_ISM8_DPT_Bool                | bool
_ISM8_DPT_Enable              | bool
_ISM8_DPT_OpenClose           | bool
_ISM8_DPT_Scaling             | float
_ISM8_DPT_Value_Temp          | float
_ISM8_DPT_Value_Tempd         | float
_ISM8_DPT_Value_Pres          | float
_ISM8_DPT_Power               | float
_ISM8_DPT_TimeOfDay           | string
_ISM8_DPT_Date                | string
_ISM8_DPT_FlowRate_m3/h       | float
_ISM8_DPT_HVACMode            | integer
_ISM8_DPT_DHWMode             | integer 
_ISM8_DPT_HVACContrMode       | integer 
_ISM8_DPT_ActiveEnergy        | float
_ISM8_DPT_ActiveEnergy_kWh    | float
_ISM8_DPT_Value_Volume_Flow   | float
_ISM8_DPT_Value_1_Ucount      | integer
_ISM8_DPT_Value_2_Ucount      | integer
_ISM8_DPT_Value_Tempd_IN      | float
_ISM8_DPT_Value_1_Ucount_Erkennung | integer
 

### 6. WebFront

Name                          							| Typ     | Beschreibung
--------------------------------------------------------| ------- | ------------


### 7. PHP-Befehlsreferenz

`ISM_ReloadAllData(integer $InstanzID);`

fordert vom ISM8 eine Aktualisierung aller Daten an.

`ISM_SendData()(integer $InstanzID, $HexData);`

sendet ein hexcodiertes String zur Anlage. 

Beispiel:

`// sendet den Befehl 1x Warmwasser zur Anlage`

`$PREP_TELEGRAM = pack("H*" ,"0620F080001504000000F0C100C2000100C2000101");`

`ISM_SendData(12345, $PREP_TELEGRAM);`
