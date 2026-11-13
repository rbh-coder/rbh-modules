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

    private const ProfileList =                     'ControlInitialized';
    private const RegisterVariablesUpdateList =     '';
    private const RegisterReferenciesUpdateList =   'Id_CommunicationRunning,Id_ControlIsAlive';
    private const ReferenciesList =                 'Id_CommunicationRunning,Id_ControlIsAlive';
    private const PropertiesList =                  '';
    private const ExpertLockList =                  '';
    private const ExpertHideList =                  '';
    private const SyncList =                        '';

    private const  Transparent = 0xffffff00;
    private const  Red = 0xFF0000;
    private const  Yellow = 0xFFFF00;
    private const  Green=0x00FF00;
    private const  Blue=0x0000FF;

    private const DarkGreen = 0x24F065;
    private const DarkBlue  = 0x0D0FE4;

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
        $referenciesList="";
        $form=json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->RegisterPropertiesFromRowJson  ($form,"ControlParameters","P_","float");
        $referenciesList = $this->RegisterPropertiesFromRowJson  ($form,"ControlParameters","Id_","integer");
        $referenciesList =  $referenciesList.','.$this->RegisterPropertiesFromFlatJson ($form,"ControlMonitoring","Id_","integer");
        $referenciesList= trim($referenciesList,",");
        IPS_LogMessage(__FUNCTION__,$referenciesList);
        $this->RegisterPropertyString('ReferenciesList',$referenciesList); 
       
        ########## Timer
       //------------------------------------------------------------------------------------------------------------------
       //Timer ------------------------------------------------------------------------------------------------------------
       $this->RegisterTimer('HCP_HeartbeatTimer', 0, 'HCP_HeartbeatTimer($_IPS[\'TARGET\']);');
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
      
        $this->RegisterReferenceVarIdList($this->ReadPropertyString('ReferenciesList'));
        //Alle benötigten aktiven Referenzen für die Messagesink anmelden
        $this->RegisterPropertiesUpdateList(self::RegisterReferenciesUpdateList);
        $this->RegisterVariablesUpdateList(self::RegisterVariablesUpdateList);
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

   #################### Request action
   public function RequestAction($Ident, $Value)
   {
          switch($Ident) 
          {
            case "OpMode":
               $this->SetValue($Ident, $Value);
               break;
            case "BoostMode":
               $this->SetValue($Ident, $Value);
               break;
            case "HeatPumpReleasePower":
               $this->SetValue($Ident, $Value);
               break;
       }
   }

   private function RegisterPropertiesFromRowJson(array $form,string $group,string $identifier, string $type) : string
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
                        if ($subElement['type'] === 'RowLayout' && isset($subElement['items'])) 
                        {
                            $this->RegisterItems ($subElement,$identifier,$type,$result);
                            /*
                            foreach ($subElement['items'] as &$parElement) 
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
                            */
                        }
                    }
                }
            }
        }
        return trim($result,",");
    }

   private function RegisterPropertiesFromFlatJson(array $form,string $group,string $identifier, string $type) : string
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
                    $this->RegisterItems ($element,$identifier,$type,$result);
                    /*
                    foreach ($element['items'] as &$parElement) 
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
                    */
                }
            }
        }
        return trim($result,",");
   }

   private function RegisterItems (array $subElement,string $identifier,string type,&$result)
   {
        foreach ($subElement['items'] as &$parElement) 
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
   }

    private function WriteInstance (&$parElement)
    {
        $state = $status ? "On" : "Off";
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. $switchName: '. $switchName. ' $status: '. $state, 0);
        if (isset($parElement['name']))  
        {  
            $value=-1.0;
            $name = $parElement['name'];    
            if (str_starts_with($name,'P_')) 
            {
                $value = $this->ReadPropertyFloat($parElement['name']);
            }
            else if (str_starts_with($name,'Id_')) 
            {
                $value = $this->ReadPropertyFloat($name);
                $id = $this->ReadPropertyInteger($switchName);
                if (($id>1) && ($value> -1)) 
                {
                    //IpSymcon "RequestAction" aufrufen, das ist nicht $this->RequestAction !
                    RequestAction($id,$value);
                }
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
}

