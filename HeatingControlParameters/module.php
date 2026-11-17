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
  
    //Statische Listen für die verschiedenen Registrierungen, die zum Teil auf dynamische Listen umkopiert und ergänzt werden
    private const ProfileList =                     'ControlInitialized';           //direkt verwendet
    private const RegisterReferenciesUpdateList =   'Id_ControlHasStarted';      //direkt verwendet
    private const ReferenciesList =                 '';                             //wird als Init für dynamische 'ReferenciesList' verwendet
  
    //Konstante für verwendete Namen
    private const StaticControlParameters       = 'StaticControlParameters';
    private const ConfiguredControlParameters   = 'ConfiguredControlParameters';
    private const DynamicControlParameters      = 'DynamicControlParameters';
    private const ControlMonitoring             = 'ControlMonitoring';
    private const ReferenciesUpdateList         = 'ReferenciesUpdateList';

    //Farbdefinitionen für Profil
    private const Transparent = 0xffffff00;
    private const Yellow = 0xFFFF00;


    # SYMCON METHODS ###################################################################################################
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
        $this->DeleteProfileList (self::ProfileList);

        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $configuredControlParameters = $this->CreateStaticControlParametersList($form);
       
        //PropertyStringArrays für die benötigten Listen anlegen
        $this->RegisterPropertyString(self::ReferenciesUpdateList, '[]');
        $this->RegisterPropertyString(self::ConfiguredControlParameters, $configuredControlParameters);
        $this->RegisterPropertyString(self::ControlMonitoring, '[]');
        $this->RegisterPropertyString(self::DynamicControlParameters, '[]'); //aktuell nicht verwendet
     
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
        $this->RegisterPropertiesFromJson  ($form,self::StaticControlParameters,"P_","float");
        $this->RegisterPropertiesFromJson  ($form,self::ControlMonitoring,"P_","integer");
        
        //Alle hart codierten Referenzen aus ReferenciesList übernehmen   
        $referenciesList= self::ReferenciesList;
        //dann alle "Id_" Properties aus statischer Json nachladen
        $referenciesList = $this->AddToStringList($referenciesList,$this->RegisterPropertiesFromJson ($form,self::StaticControlParameters,"Id_","integer"));
        $referenciesList = $this->AddToStringList($referenciesList,$this->RegisterPropertiesFromJson ($form,self::ControlMonitoring,"Id_","integer"));
        //Die Liste als Property speichern
        $this->RegisterPropertyString('ReferenciesList',$referenciesList); 
       
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
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }
        ########## WebFront options

        ########## References and Messages


        //Register references and messages
        $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);     
      
        //Alle in Create() gesammelten IDs für Referenzen anmelden
        $this->RegisterReferenceVarIdList($this->ReadPropertyString('ReferenciesList')); //Alle in Create() gesammelten Variablen registrieren
        $this->RegisterReferencesUpdateList();                                           //Liste mit dynamischen Variablen registrieren und für Messsagesink anmelden

        //Alle statischen Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
       
        $this->SetTimerInterval($this->CreateName('HeartbeatTimer'),$this->ReadPropertyInteger('P_MonitoringInterval')*1000);
        $this->ResendAllControlParameters();

        

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
                if ($this->ReadPropertyInteger('Id_ControlHasStarted') == $SenderID)
                {
                    if (GetValue($SenderID)) $this->ResendAllControlParameters();
                }
                else 
                {
                    $this->SendDynamicControlParameter($SenderID);
                }

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

        if (!isset($form['elements']) || !is_array($form['elements'])) return '';
        if (!in_array($type, ['float', 'integer'], true)) return '';

        $registeredProperties = [];
        foreach ($form['elements'] as $element) {
            if (($element['type'] ?? '') !== 'ExpansionPanel'
                || ($element['name'] ?? '') !== $group
                || !isset($element['items'])
                || !is_array($element['items'])) {
                continue;
            }

            foreach ($element['items'] as $item) {
                $properties = isset($item['items']) && is_array($item['items'])
                    ? $item['items']
                    : [$item];

                foreach ($properties as $property) {
                    $name = $property['name'] ?? '';
                    if (!str_starts_with($name, $identifier)) continue;

                    $default = $property['default'] ?? 0;
                    if ($type === 'float') {
                        $this->RegisterPropertyFloat($name, (float) $default);
                    } else {
                        $this->RegisterPropertyInteger($name, (int) $default);
                    }

                    $registeredProperties[] = $name;
                }
            }
        }

        return implode(',', $registeredProperties);
    }

    private function SendConfiguredControlParameters() : void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $controlParameters = json_decode($this->ReadPropertyString(self::ConfiguredControlParameters), true);
       
        if (!is_array($controlParameters)) return;
        foreach ($controlParameters as $controlParameter) 
        {
            if (!is_array($controlParameter))
            {
                $this->SendDebug(__FUNCTION__, 'controlParameter ist kein array', 0);
                continue;
            }

            $propertyName = $controlParameter['PropertyName'] ?? '';
            $variableIdName = $controlParameter['VariableId'] ?? '';
            if ($propertyName === '' || $variableIdName === '')
            {
                $this->SendDebug(__FUNCTION__, 'propertyName oder variableIdName ist leer', 0);
                continue;
            }

            $variableId = $this->ReadPropertyInteger($variableIdName);
            if (!IPS_VariableExists($variableId))
            {
                $this->SendDebug(__FUNCTION__, 'Variable ' . $variableId . ' existiert nicht.', 0);
                continue;
            }

            $variable = IPS_GetVariable($variableId);
            if (!($variable['VariableAction'] ?? false)) {
                $this->SendDebug(__FUNCTION__, 'RequestAction ist für Variable ' . $variableId . ' nicht verfügbar.', 0);
                continue;
            }

            $value = $this->ReadPropertyFloat($propertyName);
            $this->SendDebug(__FUNCTION__, 'Sende Wert ' . $value . ' an Instanz ' . $variableId, 0);
            RequestAction($variableId, $value);
        }
    }


    private function CreateStaticControlParametersList(array $form) : string
    {

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);

        $controlParameters = [];
        if (!isset($form['elements']) || !is_array($form['elements'])) return '[]';

        foreach ($form['elements'] as $element) {
            if (($element['type'] ?? '') !== 'ExpansionPanel'
                || ($element['name'] ?? '') !== self::StaticControlParameters
                || !isset($element['items'])
                || !is_array($element['items'])) {
                continue;
            }

            foreach ($element['items'] as $row) {
                if (($row['type'] ?? '') !== 'RowLayout' || !isset($row['items'])) continue;

                $propertyName = null;
                $variableId = null;
                foreach ($row['items'] as $item) {
                    $name = $item['name'] ?? '';
                    if (str_starts_with($name, 'P_')) $propertyName = $name;
                    if (str_starts_with($name, 'Id_')) $variableId = $name;
                }

                if ($propertyName !== null && $variableId !== null) {
                    $controlParameters[] = [
                        'PropertyName' => $propertyName,
                        'VariableId' => $variableId
                    ];
                }
            }
        }

        return json_encode($controlParameters, JSON_UNESCAPED_UNICODE);
    }

    private function GetReferencesUpdateList() : array
    {
        $list = json_decode($this->ReadPropertyString(self::ReferenciesUpdateList), true);
        if (!is_array($list)) return [];

        $result = [];
        foreach ($list as $item) {
            if (!is_array($item)) continue;

            $sourceId = $item['SourceId'] ?? 0;
            $targetId = $item['TargetId'] ?? 0;
            if (!is_numeric($sourceId) || !is_numeric($targetId)) continue;

            $sourceId = (int) $sourceId;
            $targetId = (int) $targetId;
            if ($sourceId <= 0 || $targetId <= 0) continue;
            if (!IPS_VariableExists($sourceId) || !IPS_VariableExists($targetId)) continue;

            $result[] = [
                'SourceId' => $sourceId,
                'TargetId' => $targetId
            ];
        }
        return $result;
    }

    private function RegisterReferencesUpdateList() : void
    {
        $registeredSources = [];
        foreach ($this->GetReferencesUpdateList() as $item) {
            $sourceId = $item['SourceId'];
            $targetId = $item['TargetId'];

            $this->RegisterReference($sourceId);
            $this->RegisterReference($targetId);

            if (!in_array($sourceId, $registeredSources, true)) {
                $this->RegisterMessage($sourceId, VM_UPDATE);
                $registeredSources[] = $sourceId;
            }
        }
    }


    //Funktion sendet Wert von SourceId auf TartetId
    private function SendDynamicControlParameter(int $sourceId) : void
    {
        foreach ($this->GetReferencesUpdateList() as $item) {
            if ($item['SourceId'] !== $sourceId) continue;
            if (!IPS_VariableExists($item['TargetId'])) continue;

            $target = IPS_GetVariable($item['TargetId']);
            if (!($target['VariableAction'] ?? false)) continue;

            $this->SendDebug(__FUNCTION__, 'Sende ' . GetValue($sourceId) . ' an ' . $item['TargetId'], 0);
            RequestAction($item['TargetId'], GetValue($sourceId));
        }
    }

    private function SendAllDynamicControlParameters() : void
    {
        $updatedSources = [];
        foreach ($this->GetReferencesUpdateList() as $item) {
            $sourceId = $item['SourceId'];
            if (in_array($sourceId, $updatedSources, true)) continue;

            $this->SendDynamicControlParameter($sourceId);
            $updatedSources[] = $sourceId;
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
        if (!this->GetModBusInstanzID ($id))
        {
            $this->SetValue('ControlInitialized',false);
            return;
        }
        if (!ModBus_RequestRead($id))
        {
            $this->SetValue('ControlInitialized',false);
            $this->SendDebug(__FUNCTION__,"Wago Verbindung abgebrochen.", 0);
        }
        else
        {
            if (!$this->GetValue('ControlInitialized'))
            {
                $this->SendDebug(__FUNCTION__,"Setze Instanz ".$id." auf false", 0);
                ModBus_WriteCoil($id,false);
                $this->SendDebug(__FUNCTION__,"Wago Daten erneut übertragen gestartet.", 0);
                $this->ResendAllControlParameters();
		        $this->SetValue('ControlInitialized',true);
            }
        }
    }

    private function GetModBusInstanzID (&$id) :boolean
    {
        if (!IPS_VariableExists($id))
        {
            $this->SendDebug(__FUNCTION__,"Variable ".$id." existiert nicht", 0);
            $this->SetValue('ControlInitialized',false);
            return false;
        }

        $id= IPS_GetParent($id);
        if (!IPS_InstanceExists($id))
        {
            $this->SendDebug(__FUNCTION__,"Instanz ".$id." existiert nicht", 0);
            return false;
        }
       return true;
    }

    # EXTERNAL CALLS ###################################################################################################
    // Muss 'public' sein, damit Funktion von ausserhalb aufgerufen werden kann
    public function ResendAllControlParameters()
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);

        //Parameter Übertragen
        $this->SendConfiguredControlParameters();
        $this->SendAllDynamicControlParameters ();
        $id = $this->ReadPropertyInteger('Id_CommunicationRunning');
        if ($this->GetModBusInstanzID ($id))
        {
            ModBus_WriteCoil($id,true);
        }
    }
}

