<?php

/**
 * @project       HeatingControlParameters/
 * @file          module.php
 * @author        Alfred Schorn
 * @copyright     2026 Alfred Schorn
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '../../libs/RBH_ModuleFunctions.php';

class HeatingControlParameters extends IPSModule
{
    use RBH_ModuleFunctions;


    //Constants
    private const LIBRARY_GUID = '{EED96F29-CFDD-6B87-A175-30C34661C6DB}';
    private const MODULE_NAME = 'HeatingControlParameters';
    private const MODULE_PREFIX = 'HCP';
    private const MODULE_VERSION = '1.0, 12.11.2026';
    private const MINIMUM_DELAY_MILLISECONDS = 100;

    private const REFERENCIESLIST = 'ReferenciesList';

    //Hart codierte Listen für die verschiedenen Registrierungen, die zum Teil auf dynamische Listen umkopiert und ergänzt werden
    private const ProfileList =                     'ControlInitialized';           //direkt verwendet
    private const RegisterVariablesUpdateList =     '';                             //direkt verwendet
    private const RegisterReferenciesUpdateList =   'Id_CommunicationRunning';      //direkt verwendet
    private const ReferenciesList =                 '';                             //wird als Init für dynamische REFERENCIESLIST verwendet
    private const PropertiesList =                  '';                             //direkt verwendet
   
    private const ControlParameters = 'ControlParameters';
    private const ControlMonitoring ='ControlMonitoring';

    private const  Transparent = 0xffffff00;
    private const  Red = 0xFF0000;
    private const  Yellow = 0xFFFF00;
    private const  Green=0x00FF00;
    private const  Blue=0x0000FF;

    private const DarkGreen = 0x24F065;
    private const DarkBlue  = 0x0D0FE4;


    # SYMCON METHODS ###################################################################################################
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
        $this->DeleteProfileList (self::ProfileList);
       
        $this->RegisterPropertyString('ReferenciesUpdateList', '[]');
        $this->RegisterPropertyString('VariablesUpdateList', '[]');
       
        //Profile erstellen 
        $variable = 'ControlInitialized';
        $profileName = $this->CreateProfileName($variable);
        if (!IPS_VariableProfileExists($profileName)) 
        {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Nein", "", self::Transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ja", "", self::Yellow);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Control initialized'),$profileName, 10);
        $this->EnableAction($variable);


        //Alle Properties aus form.json registrieren
        $form=json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->RegisterPropertiesFromJson  ($form,self::ControlParameters,"P_","float");
        $this->RegisterPropertiesFromJson  ($form,self::ControlMonitoring,"P_","integer");
        
        //Alle hart codierten Referenzen aus ReferenciesList übernehmen   
        $referenciesList= self::ReferenciesList;
        //dann alle "Id_" Properties aus Json nachladen
        $referenciesList = $this->AddToStringList($referenciesList,$this->RegisterPropertiesFromJson ($form,self::ControlParameters,"Id_","integer"));
        $referenciesList = $this->AddToStringList($referenciesList,$this->RegisterPropertiesFromJson ($form,self::ControlMonitoring,"Id_","integer"));
        //Die Liste als Property speichern
        $this->RegisterPropertyString(self::REFERENCIESLIST,$referenciesList); 
       
        ########## Timer
       //------------------------------------------------------------------------------------------------------------------
       //Timer ------------------------------------------------------------------------------------------------------------
       $this->RegisterTimer($this->CreateName('HeartbeatTimer'),$this->ReadPropertyInteger('P_MonitoringInterval')*1000,$this->CreateName('HeartbeatTimer').'($_IPS[\'TARGET\']);');
       //------------------------------------------------------------------------------------------------------------------
    }

     //Wird aufgerufen, wenn in der Form für das Module was geändert wurde und das "Änderungen Übernehmen" bestätigt wird.
    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all message registrations

        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == EM_UPDATE) {
                    $this->UnregisterMessage($senderID, EM_UPDATE);
                }
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }
        ########## WebFront options

        ########## References and Messages


        //Register references and messages
        $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);     
      
        $this->RegisterReferenceVarIdList($this->ReadPropertyString(self::REFERENCIESLIST));
        //Alle benötigten aktiven Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
        $this->RegisterVariablesUpdateList(self::RegisterVariablesUpdateList);
        
        $this->SetTimerInterval($this->CreateName('HeartbeatTimer'),$this->ReadPropertyInteger('P_MonitoringInterval')*1000);

        $form=json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->SendPropertyValuesFromJson($form,self::ControlParameters);

        $this->Test();
        ########## Timer

        ########## Profile

        ########## Misc
    }


    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
       $this->DeleteProfileList (self::ProfileList);
       IPS_LogMessage( $this->InstanceID,'Destroy Methode ausgeführt.');
    }

    //wird aufgrufen wenn variable mit
    //void registermessage (integer $senderid, integer $nachrichtid)
    //registriert wird
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                //Hier ist "OnChange" ausprogrammiert, d.h. wenn es keine Differenz zm alten Wert gibt, dann Abflug
                if ($Data[1]==0) return;
                $this->SendDebug(__FUNCTION__, 'Wert hat sich auf ' . $Data[0] . ' geändert.', 0);

                //Wenn die Änderung von der Instanz kommt, die die Kommunikation mit der Wago steuert, dann alle Parameter erneut senden
                if ($this->ReadPropertyInteger('Id_CommunicationRunning') == $SenderID)
                {
                    $this->ResendAllControlParameters();
                }

                break;

            case EM_UPDATE:
            case EM_CHANGEACTIVE:
            case EM_CHANGESCHEDULEGROUPPOINT:
            case EM_CHANGETRIGGER:

                //$Data[0] = last run
                //$Data[1] = next run

                //Weekly schedule
                $this->SendDebug(__FUNCTION__, 'Trigger durch'.$Message.'.', 0);
                $this->SendDebug(__FUNCTION__, 'Data[] = ' . json_encode($Data). ' ID: '. $SenderID, 0);
               
                break;
        }
    }
    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    #################### Request action
    public function RequestAction($Ident, $Value)
    {
        switch($Ident) 
        {
            case "ControlInitialized":
                $this->SetValue($Ident, $Value);
                break;
        }
    }

    # USER METHODS ###################################################################################################
    private function RegisterPropertiesFromJson(array $form,string $group,string $identifier, string $type) : string
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        
        $result="";
        if ($form == null) return result;  
        
        foreach ($form['elements'] as &$element) 
        {
            if ($element['type'] === 'ExpansionPanel' && isset($element['name'])) 
            {
                if ($element['name'] === $group && isset($element['items'])) 
                {
                    foreach ($element['items'] as &$subElement) 
                    {
                        if (isset($subElement['items'])) 
                        {
                            $this->RegisterItems ($subElement,$identifier,$type,$result);
                        }
                        else 
                        {
                                $this->RegisterItem ($subElement,$identifier,$type,$result);
	                    }
                    }
                }
            }
        }
        return trim($result,",");
    }

    private function SendPropertyValuesFromJson(array $form,string $group)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       
        if ($form == null) return;  
        
        foreach ($form['elements'] as &$element) 
        {
            if ($element['type'] === 'ExpansionPanel' && isset($element['name'])) 
            {
                if ($element['name'] === $group && isset($element['items'])) 
                {
                    foreach ($element['items'] as &$subElement) 
                    {
                        if (isset($subElement['items'])) 
                        {
                            $this->SendInstances($subElement);
                        }
                    }
                }
            }
        }
    }

    private function RegisterItems (array $subElement,string $identifier,string $type, &$result)
    {
        foreach ($subElement['items'] as &$parElement) 
        {   
            $this->RegisterItem ($parElement,$identifier,$type, $result);
        }
    }

    private function RegisterItem (array $parElement,string $identifier,string $type, &$result)
    {
        if (isset($parElement['name']))  
        {
            if (str_starts_with($parElement['name'],$identifier)) 
            {
                $default = 0;
                if (isset($parElement['default'])) $default = $parElement['default'];
                $item = $parElement['name'];
                switch ($type)
                {
                    case "float":
                    $this->RegisterPropertyFloat($item,$default);
                    $result = $result.$item.',';
                    break;
                    case "integer":
                    $this->RegisterPropertyInteger($item,$default);
                    $result = $result.$item.',';
                    break;
                }                 
            }
        }
    }

    //Die Property-Werte und zu übertragendende VariablenId sind in form.json paarweise angeordnet. So werden sie auch abearbeitet.
    //Der Wert vom Property geholt und auf die SendeInstanz geschrieben
    private function SendInstances (array $subElement)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $value=-1.0;
        $id=0;
        foreach ($subElement['items'] as &$parElement) 
        {   
            $this->ConfigureInstance ($parElement,$value,$id);
        }
        if (!IPS_VariableExists($id)) return;
        if ($value <0) return; 
        
        //Ipsymcon "RequestAction" aufrufen, das ist nicht $this->RequestAction!
        $this->SendDebug(__FUNCTION__, 'Sende '.$value.' an '. $id, 0);
        RequestAction($id,$value);
        
    }

    private function ConfigureInstance (array $parElement,&$value,&$id)
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if (isset($parElement['name']))  
        {  
            $name = $parElement['name'];    
            if (str_starts_with($name,'P_')) 
            {
                $value = $this->ReadPropertyFloat($name);
            }
            else if (str_starts_with($name,'Id_')) 
            {
                $id = $this->ReadPropertyInteger($name);
            }  
        }
    }

    private function RegisterVariablesIdsFromJson(array $form,string $group,string $identifier) 
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($form == null) return;  

        foreach ($form['elements'] as &$element) 
        {
            if ($element['type'] === 'ExpansionPanel' && isset($element['name'])) 
            {
                if ($element['name'] === $group && isset($element['items'])) 
                {
                    foreach ($element['items'] as &$subElement) 
                    {
                        if ($subElement['type'] === 'RowLayout' && isset($subElement['items'])) 
                        {
                            foreach ($subElement['items'] as &$parElement) 
                            {   
                                if (isset($parElement['name']) && isset($parElement['default']))  
                                {
                                    if (str_starts_with($parElement['name'],$identifier) ) 
                                    {
                                        $this->RegisterPropertyFloat($parElement['name'],$parElement['default']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function Test ()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $form = $this->GetConfigurationForm();
        if (empty($form)) return;
        var_dump ($form);
        
        $configpage = json_decode($form);
        var_dump ($configpage);
    }

    private function SendListItemsFromJason (array $form,string $group)
    {
        foreach ($form['elements'] as &$element) 
        {
            if ($element['type'] === 'ExpansionPanel' && isset($element['name'])) 
            {
                if ($element['name'] === $group && isset($element['items'])) 
                {
                    foreach ($element['items'] as &$subElement) 
                    {
                        if ($subElement['type'] === 'List' && isset($subElement['columns'])) 
                        {
                            $this->SendListItems ($subElement['columns']);
                        }
                    }
                }
            }
        }
    }

    private function SendListItems (array $item)
    {
         // JSON-String aus der Konfiguration laden und in ein Array umwandeln
        $liste = json_decode($item, true);

        // Prüfen, ob gültige Daten vorhanden sind
        if (is_array($liste)) 
        {
            foreach ($liste as $index => $zeile) 
            {
                // Zugriff über die in der form.json definierten Spaltennamen ("name")
                $sourceId = $zeile['SourdeId'];
                $targetId = $zeile['TargetId'];

                // Beispiel: Verarbeitung der Zeilenwerte
                $this->SendDebug('Zeile ' . $index, 'Source: ' . $sourceId . ' | Target: ' . $targetId, 0);
            
                if ($sourceId > 0 && IPS_VariableExists($sourceId) && $targetId > 0 && IPS_VariableExists($targetId)) 
                {
                    // Hier eigene Logik ausführen, z. B. Wert auslesen oder Event registrieren
                    $sourceValue = GetValue($sourceId);
                    RequestAction($targetId,$sourceValue);
                }
            }
        }
    }

    //Einen übergebenen Namen modulspezifisch aufbereiten
    private function CreateName (string $name) : string
    {
        return self::MODULE_PREFIX.'_'.$name;
    }

    //durch "," getrennte Listenwerte concatenieren und gesäubert zurückliefern
    private function AddToStringList (string $list, string $addList) : string
    {
        $newList = $list.','.$addList;
        return trim($newList,",");
    }

    // Muss 'public' sein, damit der Timer die Funktion aufrufen kann
    public function HeartbeatTimer()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        
        $id = $this->ReadPropertyInteger('Id_CommunicationRunning');
        if (!IPS_InstanceExists($id)) return;      
        $id= IPS_GetParent($id);
        
        if (!ModBus_RequestRead($id))
        {
            $this->SetValue('ControlInitialized',false);
            $this->SendDebug(__FUNCTION__,"Wago Verbindung abgebrochen.", 0);
        }
        else
        {
            if (!GetValue('ControlInitialized'))
            {
                ModBus_WriteCoil($id,false); 
                $this->ResendAllControlParameters();
		        $this->SetValue('ControlInitialized',true);
                $this->SendDebug(__FUNCTION__,"Wago Daten erneut übertragen gestartet.", 0);
            }
        }
    }

    # EXTERNAL CALLS ###################################################################################################
    // Muss 'public' sein, damit Funktion von ausserhalb aufgerufen werden kann
    public function ResendAllControlParameters()
    {
        $form=json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->SendPropertyValuesFromJson($form,self::ControlParameters);
    }
}

