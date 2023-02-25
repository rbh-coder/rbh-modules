# Heating Zone Controller

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

Das Modul ist primär zur Parametrierung bzw. Visualisierung einer Mehrzonen-Mischerkreisregelung (realisiert in WAGO-SPS mit Codesys)
gedacht. Die Kommunikation erfolgt aktuell über Modbus-TCP. Dies ist jedoch für die Funktion des Moduls nicht von Bedeutung. 

### Inhaltsverzeichnis
### 1. Funktionsumfang

Die Zielsteuerung kennt drei Betriebszustände:
* Aus
Die betroffene Zone der Heizungsteuerung ist ausgeschaltet. D.h.Heizungsmischer wird zugefahren. Die Heizungspumpe ist ausgeschaltet. 

* Hand
Die betroffene Zone der Heizungsteuerung ist im manuellen Betrieb. D.h.Heizungsmischer wird entsprechend der Aussentemperatur-Vorlaufkennlinie geregelt. Die Heizungspumpe ist immer eingeschaltet.
Ein ggf. vorhandenen Raumthermostat wird ignoriert.

* Automatik
Die betroffene Zone der Heizungsteuerung ist im Automatikbetrieb. D.h.Heizungsmischer wird entsprechend der Aussentemperatur-Vorlaufkennlinie geregelt. Die Heizungspumpe wird von einem eventuell 
(konfigurierbar) vorhandenen Raumthermostat gesteuert. Schaltet der Raumthermostat ab, schaltet die Heizungspumpe ab. Der Heizungsmischer bleibt in der atkuellen Position.

* Modulfunktion:
Das vorliegenede Modul bildet die hier eingestellbaren Betriebzustände in der Zielsteuerung folgend ab:

"Aus" und "Handbetrieb werden unverändert weitergereicht.
In der Betriebart "Automatik" wird eine Wochenschaltuhr eingeblendet und -falls konfiguriert- der Status eines Raumthermostates.
Ist der Schaltuhrstatus "Inaktiv" wird an die überlagerte Steuerung der Status "Aus" geschickt, ansonst "Automatik".

Die tatsächlich an die Zielsteuerung gesendete Betriebsart ist an der Variable "Aktive Betriebsart" ersichtlich.

Die Variable "Raumtemperatur anpassen" dient zur Beeinflussung der Vorlaufkennlinie.

Alle in der Konfiguration einstellbaren Eigenschaften mit Links auf andere IP-Symcon Variable dienen nur zur Visualisierung der Steuerung und können über das Modul nicht beeinflußt werden. 
 
### 2. Voraussetzungen

- IP-Symcon ab Version 6.0

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen: https://github.com/rbh-coder/modules

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Heating Zone Controller'-Modul mithilfe des Schnellfilters gefunden werden.  
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
`HZCTRL_BeispielFunktion(12345);`
```