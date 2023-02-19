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

* Auslesen diverser Variablen wie Wasservolumen, Ventilstatus, Wasserdruck u.v.a.m 

### 2. Voraussetzungen

- IP-Symcon ab Version 6.3

### 3. Software-Installation

* Über den Module Store das 'Wolf ISM8'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/lorbetzki/net.lorbetzki.wolfism8.git

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Wolf ISM8'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name          				     | Beschreibung
-------------------------------- | -------------------------------------------------------
			                     | 
            					 | 
                                 | 
   								 | 

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name                          							| Typ     | Beschreibung
----------------------------- 							| ------- | ------------


#### Profile

Name                    | Typ
------------------------| -------

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
