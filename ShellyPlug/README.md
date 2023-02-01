# ShellyPlug
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Das Modul steuert Shelly Wifi PLugs via REST API (https://shelly-api-docs.shelly.cloud/gen1/#shelly-plug-plugs-relay-0)

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen: https://github.com/rbh-coder/modules

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Shelly Plug'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
 IP Adresse        | IP Adresse des Plugs
 Schaltvariable        | ID einer Variable bei deren Änderung der Schaltbefehl gesendet wird
 Abfragezeit        | Zeit in Sekunden in deren Raster eine Statusabfrage gesendet wird. Zeit 0 bedeutet zyklisches Polling ist ausgeschaltet.
 Debug Modus        | Bei Debugmodus gleich "true", werden Logeinträge mittels IPS_LogMessage erzeugt.
  

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
       |         |
       |         |

#### Profile

Name   | Typ
------ | -------
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean SPL_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`SPL_BeispielFunktion(12345);`
