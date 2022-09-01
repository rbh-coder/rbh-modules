# LightColorChanger
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

* Das Modul steuert einen Farbwechselgerät mit vier verschiedenen Lampen, wie es in Wellnesseinrichtungen zu finden ist.
* Die Aktoren und Statussignale müssen auf der Konfigurationsseite ausgewählt werden.
* Die Aktoren müssen übewr "RequestAction" schaltbare Variabale sein. 
* Die Statussignale können beliebige boolsche Variable sein.
* Auf der Konfigurationsseite kann eine Überblendfunktion aktiviert werden. Damit wird die zusätzliche Variablen "Farbüberblendzeit" angelegt. Ist diese
  Zeit größer 0 sind diese Zeit lang jeweils zwei Lampen gleichzeitig beim Umschalten von einer auf die nächste Farbe aktiv.
* Auf der Konfigurationsseite wird die maximale Zeit für das Variablenprofil der Variable "Farbwechselzeit" definiert.
* Auf der Konfigurationsseite kann eine Variablen-ID für einen Expertenmodus definiert werden. Existiert diese boolsche Variable, werden Variablen, die im 	         Normalbetrieb nicht verstellt werden sollen, ausgeblendet wenn der Wert der Variable "false" ist. Das sind:
  	* "Farbwechselzeit" 
	* "Farbüberblendzeit" (sofern überhaupt aktiviert)
	* "Putzmodus".
* Generell werden in Abhängigkeit der Variable "Betriebsart" verschiedene im jeweiligen Betriebsmodus unnötige Zeilen ausgeblendet.
* In der Betriebsart "Automatik" muss zusätzlich die Variable "Automatikfreigabe" gesetzt sein, damit der Farbwechsel läuft. Die Idee dahinter ist, dass die 
  Betriebsart normalerweise immer auf "Automatik" steht und hier die externe Steuerung über das Signal "Automatikfreigabe" passiert.
* Die Variable "Putzmodus" schaltet in jeder Betriebsart alle Lampenausgänge ein, um eben maximale Helligkeit zu bieten. Bei Setzen des Schalters "Automatikfreigabe"     wird diese Variable auf jeden Fall zurückgesetzt. Der Grund ist, wenn der "Putzmodus" von extern gesetzt wurde (z.B. KNX Taster) und vergessen wird   	         zurückzusetzen, soll dies spätestens bei Start des Automatikbetriebs passieren. Zusätzlich ist der Putzmodus maximal eine auf der Konfigurationsseite einstellbare     Zeit aktiv.

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen: https://github.com/rbh-coder/modules

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Light Color Changer'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
 Maximale Farbwechselzeit        | Maximale Zeit in Minuten des automatisch erstellten Profils LCC_ColorChangeTime 
 Überblenden verwenden        | Aktiviert bzw deaktiviert eine Überblendfunktionen beim Umschalten von einer Lampe auf die nächste.

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

`boolean LC_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`LC_BeispielFunktion(12345);`
