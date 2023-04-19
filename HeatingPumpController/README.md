# Heating Pump Controller

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

Das Modul ist primär zur Parametrierung bzw. Visualisierung einer Mehrzonen-Mischerkreisregelung (realisiert in WAGO-SPS mit Codesys)
gedacht. Die Kommunikation erfolgt aktuell über Modbus-TCP. Dies ist jedoch für die Funktion des Moduls nicht von Bedeutung. 

### Inhaltsverzeichnis
### 1. Funktionsumfang 
### 2. Voraussetzungen

- IP-Symcon ab Version 6.0

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen: https://github.com/rbh-coder/modules

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Heating Pump Controller'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
         |
         | 
         |
         |
  

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

`boolean HZCTR_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`HPCTRL_BeispielFunktion(12345);`
```