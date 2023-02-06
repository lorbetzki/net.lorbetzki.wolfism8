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

 Unter 'Instanz hinzufügen' kann das 'Pontos Base V2'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name          				     | Beschreibung
-------------------------------- | -------------------------------------------------------
 IP Adresse                      | IP Adresse oder Hostname des Gerätes
Intervall in sek                 | Abrufintervall. 0 deaktiviert das automatische abrufen
Auswahl Standardvariablen        | Auswahl diverser Variablen
Auswahl zusätzlicher Variablen   | Auswahl diverser Variablen

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

`PB_UpdateData(integer $InstanzID);`

Aktualisierung aller Daten.

`PB_GetData(integer $InstanzID, string $Key="all");`

wird `PB_GetData(12345);` ohne Paramter aufgerufen werden viele Daten abgeholt, jedoch nicht alle. Möchte man bestimmte Daten. Bspw. 
`PB_GetData(12345, "CND");` wird die Wasserleitfähigkeit abgeholt.
