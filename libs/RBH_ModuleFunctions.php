<?php

/**
 * @project
 * @file          RBH_ModuleFunctions.php
 * @author        Alfred Schorn
 * @copyright     2023 Alfred Schorn
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RBH_ModuleFunctions
{
    private function RegisterVariableIds(string $itemsString) : void
    {
        if (!$this->IsValidStringList($itemsString)) return;
        foreach (explode(',', $itemsString) as $item) {
            if ($item != "") $this->RegisterPropertyInteger($item, 0);
        }
    }

    private function RegisterLinkIds(string $itemsString) : void
    {
        if (!$this->IsValidStringList($itemsString)) return;
        foreach (explode(',', $itemsString) as $item) {
            $this->RegisterAttributeInteger($item, 0);
        }
    }

    private function RegisterPropertiesUpdateList(string $itemsString) : void
    {
        if (!$this->IsValidStringList($itemsString)) return;
        foreach (explode(',', $itemsString) as $item) {
            $this->RegisterStatusUpdate($item);
        }
    }

    private function RegisterVariablesUpdateList(string $itemsString) : void
    {
        if (!$this->IsValidStringList($itemsString)) return;
        foreach (explode(',', $itemsString) as $item) {
            $id = $this->GetIDForIdent($item);
            if ($this->IsValidId($id)) {
                $this->RegisterMessage($id,VM_UPDATE);
                }
        }
    }

    private function CreateLink (int $targetID,string $name,string $iconName, int $position) : int
    {
        $linkID = @IPS_GetLinkIDByName($name, $this->InstanceID);
        if ($this->IsValidId($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && !$linkID) {
                $linkID = IPS_CreateLink();
            }
            else {
	            IPS_DeleteLink($linkID);
                $linkID = IPS_CreateLink();
            }

            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID,$position);
            IPS_SetName($linkID, $name);
            IPS_SetIcon($linkID,$iconName);
            IPS_SetLinkTargetID($linkID, $targetID);
            return $linkID;

        } else {
            if (is_int($linkID)) {
                 IPS_DeleteLink($linkID);
            }
        }
         return 0;
    }

    //Methode Registriert Variable für die MessageSink, soferne diese in der Modul-Form aktiviert ist
    private function RegisterStatusUpdate(string $variableName) : void
    {
        if (!is_string($variableName)) return;
        if (strlen($variableName) == 0) return;
        $id= $this->ReadPropertyInteger($variableName);
        if ($this->IsValidId($id)) {
            $this->RegisterMessage($id,VM_UPDATE);
        }
    }

   private function HandleExpertSwitch(int $id, string $hideList, string $lockList ) : bool
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $status = GetValueInteger($id) == 0;
       if ($id==0)  $status = false;

       if ($this->IsValidStringList($hideList))
       {
           foreach (explode(',', $hideList) as $item)
           {
               $this->HideItem($item,$status);
           }
       }
       if ($this->IsValidStringList($lockList))
       {
           foreach (explode(',',$lockList) as $item)
           {
               $this->LockItem($item,$status);
           }
       }
       return $status;
   }

   private function IsValidStringList(string $list) : bool
   {
      return $this-> IsValidString($list);
   }

   private function IsValidString(string $value) : bool
    {
       if (!is_string($value)) return false;
       if (strlen($value) == 0) return false;
       return true;
    }

   private function IsValidId(int $id) : bool
   {
      // $this->SendDebug(__FUNCTION__, 'ID:'.$id.' Exists: '. @IPS_ObjectExists($id), 0);
      
       return (($id>0) && @IPS_ObjectExists($id));
   }

   private function ValidateEventPlan(int $weekTimerId) : bool
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $result = false;
       if (!$this->IsValidId($weekTimerId)) return $result;

       $event = IPS_GetEvent($weekTimerId);
       if ($event['EventActive'] == 1) {
           $result = true;
       }

       return $result;
   }

   private function GetWeekTimerAction(int $weekTimerId) : int
   {
       $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
       $actionID = 0;
       if (!$this->IsValidId($weekTimerId)) return $actionID;

       $event = IPS_GetEvent($weekTimerId);
       if (!$event['EventActive']) return 0;
       $timestamp = time();
       $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
       $weekDay = date('N', $timestamp);
       foreach ($event['ScheduleGroups'] as $group) {
           if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
               $points = $group['Points'];
               foreach ($points as $point) {
                   $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                   if ($startTime <= $searchTime) {
                       $actionID = $point['ActionID'];
                   }
               }
           }
       }

       return $actionID;
   }

   private function DeleteProfileList (string $list) : void
   {
         if (!$this->IsValidStringList($list)) return;
         foreach ($this->GetArrayFromString($list) as $item) {
               if (is_string($item)) {
                    $cleanedItem = trim($item);
                    if (strlen($cleanedItem) > 0)
                    {
                       $this->DeleteProfile($cleanedItem);
                    }
               }
         }
   }

    private function DeleteProfile(string $profileName) : void
    {
        if (empty($profileName)) return;
        $profile =  $this->CreateProfileName($profileName);
        IPS_LogMessage( $this->InstanceID,'Lösche Profil ' .$profile . '.');
        if (@IPS_VariableProfileExists($profile)) {
               IPS_DeleteVariableProfile($profile);
        }
    }

    private function GetArrayFromString (string $itemsString) : array
    {
        return explode(',', $itemsString);
    }

    private function CreateProfileName (string $profileName) : string
    {
         return self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profileName;
    }

    private function HideItemById (int $id, bool $hide ) : void
    {
        if (!$this->IsValidId($id)) return;
        IPS_SetHidden($id,$hide);
    }
    private function LockItemById (int $id, bool $hide ) : void
    {
        if (!$this->IsValidId($id)) return;
        IPS_SetDisabled($id,$hide);
    }

    private function LockItem(string $item,bool $status) : void
    {
        $id = $this->GetIDForIdent($item);
        if (!$this->IsValidId($id)) return;
        IPS_SetDisabled($id, $status);
    }
     private function HideItem(string $item,bool $status) : void
    {
        if (empty($item)) return;
        $id = $this->GetIDForIdent($item);
        if (!$this->IsValidId($id)) return;
        IPS_SetHidden($id, $status);
    }

    #################### Private

    private function KernelReady() : void
    {
        $this->ApplyChanges();
    }
}