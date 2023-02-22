<?php

declare(strict_types=1);
class LightColorChanger extends IPSModule
{
    private const MODULE_PREFIX = 'LCC';
    private const MODULE_NAME = 'LightColorChanger';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Some color definitions
        $transparent = 0xffffff00;
        $white = 0xffffff;
        $red=0xFF0000;
        $yellow = 0xFFFF00;
        $green_blue=0x0CBAA6;
        $green=0x00FF00;
        $blue=0x0000FF;

        $this->RegisterAttributeString('SwitchList', "SwitchAmberLightID,SwitchRedLightID,SwitchGreenLightID,SwitchBlueLightID");
        $this->RegisterAttributeString('StatusList', "StatusAmberLightID,StatusRedLightID,StatusGreenLightID,StatusBlueLightID");
        $this->RegisterAttributeString('ColorList', "1,3,2,6,4,12,8,9");
        $this->RegisterAttributeString('MainColorList', "1,2,4,8");
        $this->RegisterAttributeString('ExpertListHide',"ColorChangeTime,CleaningMode");
        $this->RegisterAttributeString('ExpertListLock',"OpMode");

        $allowedColorNumbers = array_map('intval', explode(',', $this->ReadAttributeString('ColorList')));

        //Allgemeine Profile
        $variable = 'Switch';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Power");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }

       
        //-------------------------------------------------------------------------------------------------------------

        //Variable----------------------------------------------------------------------------------------------------
        //Position 0
        $variable = 'OpMode';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 2, 0);
            IPS_SetVariableProfileIcon($profileName, "Shutter");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, 1, "Hand", "", $yellow);
            IPS_SetVariableProfileAssociation($profileName, 2, "Automatik", "", $green);
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Operation Mode'),$profileName, 0);
        $this->EnableAction($variable);

        //Position 10
        $variable = 'ColorChangeTime';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileText($profileName, "", " min");
            IPS_SetVariableProfileValues($profileName, 1, 15, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }
        $this->RegisterVariableInteger( $variable, $this->Translate('Color Change Time'),$profileName, 10);
        $this->EnableAction( $variable);

        //Position 20
        $variable = 'ColorFadeTime';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileText($profileName, "", " sec");
            IPS_SetVariableProfileValues($profileName, 0, 60, 1);
            IPS_SetVariableProfileIcon($profileName, "Clock");
        }
        $this->RegisterVariableInteger( $variable, $this->Translate('Color Fade Time'),  $profileName, 20);
        $this->EnableAction( $variable);

        //Position 30
        $variable = 'ManualColorSelection';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileValues($profileName, 0, 16, 0);
            IPS_SetVariableProfileIcon($profileName, "Flower");

            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            foreach ($allowedColorNumbers as $i) {
                if (in_array($i, $allowedColorNumbers, true)) {
                    $this->CreateProfileAssociation($profileName, $i);
                }
            }
        }
        $this->RegisterVariableInteger($variable, $this->Translate('Manual Color Selection'), $profileName, 30);
        $this->EnableAction($variable);

        //Position 40
        $variable = 'ActiveColors';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 1);
            IPS_SetVariableProfileIcon($profileName, "Flower");
            IPS_SetVariableProfileAssociation($profileName, 0, "Aus", "", $transparent);
            for ($i=1; $i < 16; $i++) {
                $this->CreateProfileAssociation($profileName, $i);
            }
        }
        $this->RegisterVariableInteger( $variable, $this->Translate('Active Colors'), $profileName, 40);

        //Position 50
        $variable = 'CleaningMode';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Cleaning Modus'),$profileName, 50);
        $this->EnableAction($variable);
        $this->RegisterMessage($this->GetIDForIdent($variable),VM_UPDATE);

         //Position 60
        $variable = 'AutomaticRelease';
        $profileName = $this->CreateProfileName($variable);
        if (IPS_VariableProfileExists($profileName)) IPS_DeleteVariableProfile($profileName);
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
            IPS_SetVariableProfileIcon($profileName, "Ok");
            IPS_SetVariableProfileAssociation($profileName, false, "Aus", "", $transparent);
            IPS_SetVariableProfileAssociation($profileName, true, "Ein", "", $green);
        }
        $this->RegisterVariableBoolean($variable, $this->Translate('Automatic Release'),$profileName, 60);
        $this->EnableAction($variable);
        $this->RegisterMessage($this->GetIDForIdent($variable),VM_UPDATE);
       
        //-------------------------------------------------------------------------------------------------------------

        //Timer--------------------------------------------------------------------------------------------------------
        $this->RegisterTimer('LCC_Timer', 0, 'LCC_UpdateTimer($_IPS[\'TARGET\']);');
        $this->RegisterTimer('LCC_CleaningTimer', 0, 'LCC_StopCleaningMode($_IPS[\'TARGET\']);');
        $this->RegisterTimer('LCC_AutomaticRelease', 0, 'LCC_AutomaticRelease($_IPS[\'TARGET\']);');
        //-------------------------------------------------------------------------------------------------------------

        //Attribute----------------------------------------------------------------------------------------------------
        $this->RegisterAttributeInteger('ActColor', 0);
        $this->RegisterAttributeInteger('CleaningStatus',0);
        //-------------------------------------------------------------------------------------------------------------

        //Properties----------------------------------------------------------------------------------------------------
        $this->RegisterVariableIds($this->ReadAttributeString('SwitchList'));
        $this->RegisterVariableIds($this->ReadAttributeString('StatusList'));
        $this->RegisterPropertyInteger('ExpertModeID', 0);

        $this->RegisterPropertyInteger('MaxColorChangeTime', 15);
        $this->RegisterPropertyInteger('CleaningModeTime', 60);
        $this->RegisterPropertyBoolean('UseFading',false);
        //-------------------------------------------------------------------------------------------------------------

    }


    private function RegisterVariableIds(string $itemsString)
    {
        foreach (explode(',', $itemsString) as $item) {
            $this->RegisterPropertyInteger($item, 0);
        }
    }

    private function CreateProfileAssociation(string $profileName, int $colorCode)
    {
        //$transparent = 0xffffff00;
        //$white = 0xffffff;
        //$red=0xFF0000;
        //$yellow = 0xFFFF00;
        //$green=0x00FF00;
        //$blue=0x0000FF;

        $keyTable = array(1,2,4,8);
        $colorTable = array(0xFFFF00,0xFF0000,0x00FF00,0x0000FF);
        $colorNameTable = array('Gelb','Rot','Grün','Blau');

        $numberColors=0;
        $colorString = "";

        //Zuerst Anzahl der betroffenen Farben ermitteln.
        //Profil Assiziationstext generieren
        for ($i=0; $i < 4; $i++) {
            $key = $keyTable[$i];
            if (($key & $colorCode) > 0) {
                $numberColors++;
                $colorString= $colorString.'-'. $colorNameTable[$i];
            }
        }
        $colorCodeHex = 0;
        //Dann noch einen Durchlauf
        $byte1 =  0;
        $byte2 =  0;
        $byte3 =  0;
        for ($i=0; $i < 4; $i++) {
            $key = $keyTable[$i];

            if (($key & $colorCode) > 0) {
                $actColorCode = $colorTable[$i];
                //Farbcode byteweise bearbeiten
                $byte1 =  $this->CalculateMixedColor($numberColors, $actColorCode & 0xff, $byte1);
                $byte2 =  $this->CalculateMixedColor($numberColors, ($actColorCode >> 8) & 0xff, $byte2);
                $byte3 =  $this->CalculateMixedColor($numberColors,  ($actColorCode >> 16) &  0xff, $byte3);
            }
        }

        $colorCodeHex = $byte1 + ($byte2 << 8) + ($byte3 << 16);
        $colorString = trim($colorString, '-');
        IPS_SetVariableProfileAssociation($profileName, $colorCode, $colorString, "", $colorCodeHex);
    }

    private function CalculateMixedColor(int $numberColors, int $actNumber, int $sumNumber)
    {
        $sumNumber += $actNumber / $numberColors;
        return (int)min($sumNumber, 0xFF);
    }


    //Wird aufgerufen bei Änderungen in der GUI, wenn für Variable
    //void EnableAction (string $Ident)
    //regstriert wird
    public function RequestAction($Ident,$Value)
    {
        switch($Ident) {
            case "OpMode":
                $this->SetValue($Ident, $Value);
                $this->HandleOpMode($Value);
                break;
            case "ColorChangeTime":
                $this->SetValue($Ident, $Value);
                $this->RestartTimers();
                break;
            case "ColorFadeTime":
                $this->SetValue($Ident, $Value);
                $this->RestartTimers();
                break;
            case "ManualColorSelection":
                $this->SetValue($Ident, $Value);
                $this->SetManualColor();
                break;
            case "CleaningMode":
                $this->SetValue($Ident, $Value);
                //$this->SetCleaningMode($this->GetValue('OpMode')); //wird ohen hin bei Änderung in MessageSink verarbeitet
                break;
            case "AutomaticRelease":
                 $this->SetValue($Ident, $Value);
                //$this->StartAutomaticColor(); //wird ohenhin bei Änderung in MessageSink verarbeitet
                break;
        }
    }

    private function UpdateColorStatus(int $senderID)
    {
        $itemArray =  $this->GetArrayFromString ($this->ReadAttributeString('StatusList'));
        $idx = 0;
        $found = false;
        foreach ($itemArray as $item)
        {
           if ($senderID == $this->ReadPropertyInteger($item))
           {
               $found = true;
               break;
           }
           $idx++;
        }
        if (!$found) return;

        $colorNumber = $this->SetColorNumber ($this->GetValue('ActiveColors'),$idx,GetValueBoolean($senderID));
        $this->SetValue('ActiveColors', $colorNumber);
    }

    private function GetArrayFromString (string $itemsString)
    {
        return explode(',', $itemsString);
    }

    private function SetColorNumber (int $colorNumber, int $digit , bool $status )
    {
        if ($status)
        {
            return  $colorNumber | (pow(2,$digit));
        }
        else
        {
            return  $colorNumber & (15-pow(2,$digit));
        }
    }

    private function HandleOpMode(int $opmode)
    {
        switch($opmode) {
            case 0: //Aus
                $this->HideItem('ManualColorSelection',false);
                $this->SetCleaningMode($opmode);
                break;
            case 1: //Handbetrieb
                $this->HideItem('ManualColorSelection',false);
                $this->SetCleaningMode($opmode);
                break;
            case 2: //Automatikbetrieb
                $this->HideItem('ManualColorSelection',true);
                $this->SetCleaningMode($opmode);
                break;
            default:
        }
    }

    private function ColorManager (int $opmode)
    {
        $actualColors = $this->GetValue('ActiveColors');
        $color = $actualColors;
        $actCleaningStatus =  $this->ReadAttributeInteger('CleaningStatus');

        //IPS_LogMessage("ColorManager", '$opMode:'.$opmode.' $actCleaningStatus:'.$actCleaningStatus);

        //Ggf. laufenden Timer stoppen
        $this->SetTimerInterval('LCC_Timer', 0);

        //Falls Putzmodus nicht aktiv ist
        if ($actCleaningStatus != 1)
        {
            //IPS_LogMessage("ColorManager", 'opMode:'.$opmode);
            switch($opmode)
            {
                case 0: //Aus
                    $color=0;
                    break;
                case 1: //Handbetrieb
                    $color=$this->GetValue('ManualColorSelection');
                    break;
                case 2: //Automatikbetrieb
                    if ($this->GetValue('AutomaticRelease'))
                    {
                        $this->ChangeColor();
                        return;
                    }
                    else
                    {
                        $color=0;
                    }
            }
        }
        else
        {
             $color = 15;
        }

        if ($color != $actualColors) $this->SetActualColor ($color);
    }

    private function SetActualColor (int $color)
    {
         if ($this->ReadAttributeInteger("ActColor") ==  $color) return;
         $this->WriteAttributeInteger("ActColor",$color);
         $this->SetColor($color);
    }

    //Wird aufgrufen wenn Variable mit
    //void RegisterMessage (integer $SenderID, integer $NachrichtID)
    //registriert wird
    public function MessageSink($TimeStamp,$SenderID,$Message,$Data)
    {
        //$Data:
        //0: Aktueller Wert
        //1: Ob es eine Differenz zum alten Wert gibt.
        //2: Alter Wert

        //Hier ist "OnChange" ausprogrammiert, d.h. wenn es keine Differenz zm alten Wert gibt, dann Abflug
        if ($Data[1]==0) return;

        //$this->SendDebug("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true), 0);
        //IPS_LogMessage("MessageSink", 'id:'.$SenderID.' message:'.$Message.' data:'.print_r($Data, true));

        if ($this->ReadPropertyInteger('ExpertModeID') == $SenderID)
        {
            $this->HandleExpertSwitch($SenderID);
        }
        else if ($this->GetIDForIdent('CleaningMode') == $SenderID)
        {
             $this->SetCleaningMode($this->GetValue('OpMode'));
        }
        else if ($this->GetIDForIdent('AutomaticRelease') == $SenderID)
        {
             //IPS_LogMessage("MessageSink", 'id:'.$SenderID.' message:'.$Message);
             $this->StartAutomaticColor();
             //$this->SetTimerInterval('LCC_AutomaticRelease', 1);
        }
        //Die Statusänderung der Lampen auswerten und ggf. die "ActColor" richtig setzen
        else
        {
            $this->UpdateColorStatus($SenderID);
        }
    }

    public function UpdateTimer()
    {
        $this->ChangeColor();
    }


    public function AutomaticRelease ()
    {
        $this->SetTimerInterval('LCC_AutomaticRelease', 0);
        $this->StartAutomaticColor();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    //Wird aufgerufen, wenn in der Form für das Module was geändert wurde und das "Änderungen Übernehmen" bestätigt wird.
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

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

        //Alle definierten Status Variable der Lampen für die MessageSink anmelden
        $itemsString = $this->ReadAttributeString('StatusList');
        foreach ( $this->GetArrayFromString($this->ReadAttributeString('StatusList')) as $item) {
            $this->RegisterStatusUpdate($item);
        }

        $this->RegisterStatusUpdate('ExpertModeID');

        //Profile Grenzwert setzen
        IPS_SetVariableProfileValues("LCC_ChangeTime", 1,  $this->ReadPropertyInteger('MaxColorChangeTime'), 1);

        //False Überblendzeit deaktiviert ist, dann die Zeilen verstecken
        if (!$this->ReadPropertyBoolean('UseFading'))
        {
             $this->HideItem('ColorFadeTime',true);
             $this->SetValue('ColorFadeTime',0);
        }
        else
        {
            $this->HideItem('ColorFadeTime',false);
            $this->SetValue('ColorFadeTime',5);
        }
    }

    //Methode Registriert Variable für die MessageSink, soferne dieser in der Modul-Form aktiviert ist
    private function RegisterStatusUpdate(string $statusName)
    {
        $id= $this->ReadPropertyInteger($statusName);
        //Register for change notification if a variable is defined
        //IPS_LogMessage("ApplyChanges", 'id:'.$id.' name:'.$statusName);
        if ($id>0) {
            $this->RegisterMessage($id,VM_UPDATE);
        }
    }

    private function ChangeColor()
    {
        $allowedColorNumbers = array_map('intval', explode(',', $this->ReadAttributeString('ColorList')));
        $mainColorNumbers = array_map('intval', explode(',', $this->ReadAttributeString('MainColorList')));
        $fadeTime =  $this->GetValue("ColorFadeTime") * 1000;
        //Increment color value at each call
        $actColor =  $this->FindNextColor($this->ReadAttributeInteger("ActColor"), $allowedColorNumbers, $mainColorNumbers, $fadeTime > 0);
        $this->WriteAttributeInteger("ActColor", $actColor);
        $setInterval =  $this->GetValue("ColorChangeTime") * 1000 *60;

        //Falls Hauptfarbe aktiv ist, dann normale Wechselzeit starten
        if (in_array($actColor, $mainColorNumbers, true)) {
            $this->SetTimerInterval('LCC_Timer', $setInterval);
        }
        //ansonst Übergangszeit starten
        else {
            $this->SetTimerInterval('LCC_Timer', $fadeTime);
        }

        $this->SetColor($actColor);
    }

    private function SetManualColor()
    {
        switch($this->GetValue('OpMode'))
        {
            case 1: //Handbetrieb
                $this->UpdateCleaningStatus();
                $this->ColorManager (1);
                break;
        }
    }

    private function StartAutomaticColor()
    {
        //IPS_LogMessage("StartAutomaticColor", 'status:'.$status);
        switch($this->GetValue('OpMode'))
        {
            case 2: //Automatikbetrieb
                $this->UpdateCleaningStatus();
                $this->ColorManager (2);
                break;
        }
    }

    private function RestartTimers()
    {
        switch($this->GetValue('OpMode'))
        {
            case 2: //Automatikbetrieb
                if (!$this->GetValue('AutomaticRelease')) return;

                $mainColorNumbers = array_map('intval', explode(',', $this->ReadAttributeString('MainColorList')));
                $fadeTime =  $this->GetValue("ColorFadeTime") * 1000;
                $setInterval =  $this->GetValue("ColorChangeTime") * 1000 *60;
                $actColor =  $this->ReadAttributeInteger("ActColor");
                //Falls Haupfarbe aktiv ist, dann normale Wechselzeit starten
                if (in_array($actColor, $mainColorNumbers, true)) {
                    $this->SetTimerInterval('LCC_Timer', $setInterval);
                }
                //ansonsten Übergangszeit neu starten falls grösser 0
                else if ($fadeTime > 0) {
                    $this->SetTimerInterval('LCC_Timer', $fadeTime);
                }
                //ansonsten auf nächste Farbe schalten
                else
                {
                    $this->ChangeColor();
                }
                break;
        }
    }

    private function SetCleaningMode(int $opMode)
    {
        $actCleaningStatus =  $this->ReadAttributeInteger('CleaningStatus');
        $newCleaningStatus =  $this->CleaningStatusManager($actCleaningStatus);
        //IPS_LogMessage("SetCleaningMode", '$opMode:'.$opMode.' $actCleaningStatus:'.$actCleaningStatus.' $newCleaningStatus:'.$newCleaningStatus);
        $this->WriteAttributeInteger('CleaningStatus',$newCleaningStatus);
        $this->ColorManager($opMode);
    }

    private function UpdateCleaningStatus()
    {
         $actCleaningStatus = $this->ReadAttributeInteger('CleaningStatus');
         $newCleaningStatus = $this->CleaningStatusManager($actCleaningStatus);
         //IPS_LogMessage("UpdateCleaningStatus", 'Save $newCleaningStatus:'.$newCleaningStatus);
         $this->WriteAttributeInteger('CleaningStatus',$newCleaningStatus);
    }

    private function CleaningStatusManager(int $actCleaningStatus)
    {
        $cleaningStatus = $actCleaningStatus;
        switch($cleaningStatus)
        {
            case -1: //Undefined
            case 0: //Off
                if (!$this->IsCleaningRequested()) return;
                if ($this->IsCleaningModeAllowed())  $cleaningStatus = $this->CleaningStatusManager(2);
                else
                {
                    $cleaningStatus = $this->CleaningStatusManager(3);
                }
               break;
            case 1: //On
                 if (!$this->IsCleaningRequested() || !$this->IsCleaningModeAllowed())  $cleaningStatus = $this->CleaningStatusManager(3); //Switch Off
                break;
            case 2: //Switch On
                 IPS_LogMessage("CleaningStatusManager", $this->Translate('Start Cleaning Mode.'));
                 $this->StartCleaningMode();
                 $cleaningStatus = 1;
                break;
             case 3: //Switch Off
                 IPS_LogMessage("CleaningStatusManager", $this->Translate('Stop Cleaning Mode.'));
                 $this->StopCleaningMode();
                 $cleaningStatus = 0;
                break;
        }
        return  $cleaningStatus;
    }

    private function IsCleaningModeAllowed()
    {
        $result = true;
        switch($this->GetValue('OpMode'))
        {
            case 2: //Automatikbetrieb
               $result= !$this->GetValue('AutomaticRelease');
               break;
        }
        return  $result;
    }

    private function IsCleaningRequested()
    {
        return $this->GetValue('CleaningMode');
    }

    public function StopCleaningMode()
    {
        $this->SetTimerInterval('LCC_CleaningTimer', 0);
        $this->SetValue('CleaningMode',false);
    }

    private function StartCleaningMode()
    {
       $stopTime = $this->ReadPropertyInteger('CleaningModeTime') * 1000 *60;
       $this->SetTimerInterval('LCC_CleaningTimer', $stopTime);
    }


    private function FindNextColor(int $actColor, array $allowedColorNumbers, array $mainColorNumbers, bool $useFading)
    {
        if ($actColor == 0) {
            $actColor = reset($allowedColorNumbers);
        }
        //If actColor is equal last element in allowed list, reset to first element
        elseif ($actColor == end($allowedColorNumbers)) {
            $actColor = reset($allowedColorNumbers);
        } else {
            $idx = array_search($actColor, $allowedColorNumbers, true);
            $idx++;
            $actColor = $allowedColorNumbers[$idx];
        }
        if (!$useFading && !in_array($actColor, $mainColorNumbers, true)) {
            $actColor = $this->FindNextColor($actColor, $allowedColorNumbers, $mainColorNumbers, $useFading);
        }
        return $actColor;
    }

    private function SetColor(int $actColor)
    {
        $this->SetValue('ActiveColors', $actColor);
        $itemArray =  $this->GetArrayFromString ($this->ReadAttributeString('SwitchList'));
        $idx = 0;
        foreach ($itemArray as $item)
        {
           $this->SetLamp( $item, $actColor, pow(2,$idx));
           $idx++;
        }
    }

    //Methode setzt Lampenausgang, soferne dieser in der Modul-Form aktiviert ist
    private function SetLamp(string $switchName, int $actColor,int $mask)
    {
        $status =   ($actColor & $mask) > 0;
        $id= $this->ReadPropertyInteger($switchName);

        if ($id>0) {
            //IPS_LogMessage("SetLamp", 'id:'.$id.' value:'.$status.' switchName:'.$switchName);
            RequestAction($id, $status);
        }
    }

    private function HandleExpertSwitch(int $id)
    {
        $status = !GetValueBoolean($id);
        if ($id==0)  $status = false;
        //IPS_LogMessage("HandleExpertSwitch", 'id:'.$id.' value:'.$status);
        $itemString = $this->ReadAttributeString('ExpertListHide');
        foreach (explode(',', $itemString) as $item)
        {
            $this->HideItem($item,$status);
        }
        $itemString = $this->ReadAttributeString('ExpertListLock');
        foreach (explode(',', $itemString) as $item)
        {
            $this->LockItem($item,$status);
        }
        $this->HideItem('ColorFadeTime',$status || !$this->ReadPropertyBoolean('UseFading'));
    }

    private function HideItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetHidden($id, $status);
    }

    private function LockItem(string $item,bool $status)
    {
        $id = $this->GetIDForIdent($item);
        IPS_SetDisabled($id, $status);
    }

    private function CreateProfileName (string $profileName)
    {
        return self::MODULE_PREFIX . '_' . $profileName;
    }

}
