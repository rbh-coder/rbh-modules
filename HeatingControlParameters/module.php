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

    private const ProfileList =                     '';
    private const RegisterVariablesUpdateList =     '';
    private const RegisterReferenciesUpdateList =   '';
    private const ReferenciesList =                 '';
    private const PropertiesList =                  '';
    private const ExpertLockList =                  '';
    private const ExpertHideList =                  '';
    private const SyncList =                        '';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties
        $this->DeleteProfileList (self::ProfileList);
        /*
        $this->RegisterPropertyFloat('DesignTemperatureRadiator',55.0);
        $this->RegisterPropertyFloat('DesignTemperatureFloor',35.0);
        $this->RegisterPropertyFloat('MixerPulseLengthMax',10.0);
        $this->RegisterPropertyFloat('MixerProportionalRange',15.0);
        $this->RegisterPropertyFloat('MixerToleranceRange',1.0);
        $this->RegisterPropertyFloat('MixerCycleTime',15.0);
        */
        $this->RegisterPropertyString('ControllerVariablesList', '[]'); 
       
        $this->RegisterProperties();   
        
        //Alle in der "form.json" definierten Variablenreferenzen registrieren
        $this->RegisterVariableIds(self::PropertiesList);
        //Id Speicher für Weektimer vorbereiten
        $this->RegisterAttributeBoolean('ControllerInitialized', false);
        ########## Timer
       //------------------------------------------------------------------------------------------------------------------
       //Timer ------------------------------------------------------------------------------------------------------------
       $this->RegisterTimer('HCP_HeartbeatTimer', 0, 'HCP_HeartbeatTimer($_IPS[\'TARGET\']);');
       //------------------------------------------------------------------------------------------------------------------
    }

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

         $variables = json_decode($this->ReadPropertyString('ControllerVariablesList'), true);

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
        $this->RegisterReferenceVarIdList(self::ReferenciesList);

        foreach ($variables as $variable)
        {
                $id = $variable['VariableID'];
                $this->RegisterReference($id);
                if ($this->IsValidId($id))
                {
                    $this->RegisterMessage($id,VM_UPDATE);
                }

        }

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

    /*
    private function SelectControlAlive(int $sender) : bool
    {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $id = $this->ReadPropertyInteger('IdControlAlive');
        if (!$this->IsValidId($id)) return false;
        if ($id != $sender) return false;
        return true;
    }


   public function UpdateTimer() : void
   {
       $this->StopBoostTimer();
       $this->SetHeatPumpStatus();
   }


   private function WriteVariables () : void
   {
       $variables = json_decode($this->ReadPropertyString('ControllerVariablesList'), true);
       foreach ($variables as $variable)
       {
          $id =  $variable['VariableID'];
          if  ($this->IsValidId($id))
          {
              //Bei zumindest einem Treffer mit true zurück
              if (GetValueBoolean($id)) return true;
          }
       }
       return false;
   }
   */

    #################### Request action

   public function RequestAction($Ident, $Value)
   {
          switch($Ident) {
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

   private function RegisterProperties() 
   {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        // Lädt die originale form.json
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

    
        foreach ($form['elements'] as &$element) 
        {
            if ($element['type'] === 'ExpansionPanel' && isset($element['name'])) 
            {
                if ($element['name'] === 'ControlParameters' && isset($element['items'])) 
                {
                    foreach ($element['items'] as &$subElement) 
                    {
                        if ($subElement['type'] === 'RowLayout' && isset($subElement['items'])) 
                        {
                            foreach ($subElement['items'] as &$parElement) 
                            {   
                                if (isset($parElement['name']) && isset($parElement['default']))  
                                {
                                   if (str_starts_with($parElement['name'],'P_') ) 
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

