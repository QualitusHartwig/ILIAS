<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Class ilObjUserGUI
*
* @author Stefan Meyer <smeyer@databay.de>
* $Id$
*
* @extends ilObjectGUI
* @package ilias-core
*/

require_once "class.ilObjectGUI.php";

class ilObjUserGUI extends ilObjectGUI
{
	var $ilCtrl;

	/**
	* array of gender abbreviations
	* @var		array
	* @access	public
	*/
	var $gender;

	/**
	* ILIAS3 object type abbreviation
	* @var		string
	* @access	public
	*/
	var $type;

	/**
	* userfolder ref_id where user is assigned to
	* @var		string
	* @access	public
	*/
	var $user_ref_id;

	/**
	* Constructor
	* @access	public
	*/
	function ilObjUserGUI($a_data,$a_id,$a_call_by_reference, $a_prepare_output = true)
	{
		global $ilCtrl;

		define('USER_FOLDER_ID',7);

		$this->type = "usr";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference, $a_prepare_output);
		$this->usrf_ref_id =& $this->ref_id;

		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this,'obj_id');

		// for gender selection. don't change this
		// maybe deprecated
		$this->gender = array(
							  'm'    => "salutation_m",
							  'f'    => "salutation_f"
							  );
	}

	function &executeCommand()
	{
		global $rbacsystem;

		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		switch($next_class)
		{
			default:
				if(!$cmd)
				{
					$cmd = "view";
				}
				$cmd .= "Object";
				$this->$cmd();
					
				break;
		}
		return true;
	}


	function cancelObject()
	{
		session_unregister("saved_post");

		sendInfo($this->lng->txt("msg_cancel"),true);

		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
			$return_location = $_GET["cmd_return_location"];
			ilUtil::redirect($this->ctrl->getLinkTarget($this,$return_location));
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}
	}

	/**
	* display user create form
	*/
	function createObject()
	{
		global $rbacsystem, $rbacreview, $styleDefinition;

		if (!$rbacsystem->checkAccess('create_user', $this->usrf_ref_id) and
			!$rbacsystem->checkAccess('cat_administrate_users',$this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}

		// role selection
		$obj_list = $rbacreview->getRoleListByObject(ROLE_FOLDER_ID);

		$rol = array();
		foreach ($obj_list as $obj_data)
		{
			// allow only 'assign_users' marked roles if called from category
			if($this->object->getRefId() != USER_FOLDER_ID)
			{
				include_once './classes/class.ilObjRole.php';

				if(!ilObjRole::_getAssignUsersStatus($obj_data['obj_id']))
				{
					continue;
				}
			}
			// exclude anonymous role from list
			if ($obj_data["obj_id"] != ANONYMOUS_ROLE_ID)
			{
				// do not allow to assign users to administrator role if current user does not has SYSTEM_ROLE_ID
				if ($obj_data["obj_id"] != SYSTEM_ROLE_ID or in_array(SYSTEM_ROLE_ID,$_SESSION["RoleId"]))
				{
					$rol[$obj_data["obj_id"]] = $obj_data["title"];
				}
			}
		}

		// raise error if there is no global role user can be assigned to
		if(!count($rol))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_roles_users_can_be_assigned_to"),$this->ilias->error_obj->MESSAGE);
		}

		$keys = array_keys($rol);

		// set pre defined user role to default
		if (in_array(4,$keys))
		{
			$default_role = 4;
		}
		else
		{
			if (count($keys) > 1 and in_array(2,$keys))
			{
				$key = key($keys[2]);
				unset($keys[$key]);
			}

			$default_role = array_shift($keys);
		}

		$pre_selected_role = (isset($_SESSION["error_post_vars"]["Fobject"]["default_role"])) ? $_SESSION["error_post_vars"]["Fobject"]["default_role"] : $default_role;

		$roles = ilUtil::formSelect($pre_selected_role,"Fobject[default_role]",$rol,false,true);

		$data = array();
		$data["fields"] = array();
		$data["fields"]["login"] = "";
		$data["fields"]["passwd"] = "";
		$data["fields"]["passwd2"] = "";
		$data["fields"]["title"] = "";
		$data["fields"]["gender"] = "";
		$data["fields"]["firstname"] = "";
		$data["fields"]["lastname"] = "";
		$data["fields"]["institution"] = "";
		$data["fields"]["department"] = "";
		$data["fields"]["street"] = "";
		$data["fields"]["city"] = "";
		$data["fields"]["zipcode"] = "";
		$data["fields"]["country"] = "";
		$data["fields"]["phone_office"] = "";
		$data["fields"]["phone_home"] = "";
		$data["fields"]["phone_mobile"] = "";
		$data["fields"]["fax"] = "";
		$data["fields"]["email"] = "";
		$data["fields"]["hobby"] = "";
		$data["fields"]["default_role"] = $roles;

		$this->getTemplateFile("edit","usr");

		// fill presets
		foreach ($data["fields"] as $key => $val)
		{
			$str = $this->lng->txt($key);
			if ($key == "title")
			{
				$str = $this->lng->txt("person_title");
			}

			$this->tpl->setVariable("TXT_".strtoupper($key), $str);

			if ($key == "default_role")
			{
				$this->tpl->setVariable(strtoupper($key), $val);
			}
			else
			{
				$this->tpl->setVariable(strtoupper($key), ilUtil::prepareFormOutput($val));
			}

			if ($this->prepare_output)
			{
				$this->tpl->parseCurrentBlock();
			}
		}

		$this->ctrl->setParameter($this,'new_type',$this->type);
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		
		$this->tpl->setVariable("TXT_HEADER", $this->lng->txt($this->type."_new"));
		$this->tpl->setVariable("TXT_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->setVariable("TXT_SUBMIT", $this->lng->txt($this->type."_add"));
		$this->tpl->setVariable("CMD_SUBMIT", "save");
		$this->tpl->setVariable("TARGET", $this->getTargetFrame("save"));
		$this->tpl->setVariable("TXT_REQUIRED_FLD", $this->lng->txt("required_field"));

		$this->tpl->setVariable("TXT_LOGIN_DATA", $this->lng->txt("login_data"));
		$this->tpl->setVariable("TXT_PERSONAL_DATA", $this->lng->txt("personal_data"));
		$this->tpl->setVariable("TXT_CONTACT_DATA", $this->lng->txt("contact_data"));
		$this->tpl->setVariable("TXT_SETTINGS", $this->lng->txt("settings"));
		$this->tpl->setVariable("TXT_PASSWD2", $this->lng->txt("retype_password"));
		$this->tpl->setVariable("TXT_LANGUAGE",$this->lng->txt("language"));
		$this->tpl->setVariable("TXT_SKIN_STYLE",$this->lng->txt("usr_skin_style"));
		$this->tpl->setVariable("TXT_GENDER_F",$this->lng->txt("gender_f"));
		$this->tpl->setVariable("TXT_GENDER_M",$this->lng->txt("gender_m"));

		// FILL SAVED VALUES IN CASE OF ERROR
		if (isset($_SESSION["error_post_vars"]["Fobject"]))
		{
			foreach ($_SESSION["error_post_vars"]["Fobject"] as $key => $val)
			{
				if ($key != "default_role" and $key != "language" and $key != "skin_style")
				{
					$this->tpl->setVariable(strtoupper($key), ilUtil::prepareFormOutput($val));
				}
			}

			// gender selection
			$gender = strtoupper($_SESSION["error_post_vars"]["Fobject"]["gender"]);

			if (!empty($gender))
			{
				$this->tpl->setVariable("BTN_GENDER_".$gender,"checked=\"checked\"");
			}
		}

		// language selection
		$languages = $this->lng->getInstalledLanguages();

		// preselect previous chosen language otherwise default language
		$selected_lang = (isset($_SESSION["error_post_vars"]["Fobject"]["language"])) ? $_SESSION["error_post_vars"]["Fobject"]["language"] : $this->ilias->getSetting("language");

		foreach ($languages as $lang_key)
		{
			$this->tpl->setCurrentBlock("language_selection");
			$this->tpl->setVariable("LANG", $this->lng->txt("lang_".$lang_key));
			$this->tpl->setVariable("LANGSHORT", $lang_key);

			if ($selected_lang == $lang_key)
			{
				$this->tpl->setVariable("SELECTED_LANG", "selected=\"selected\"");
			}

			$this->tpl->parseCurrentBlock();
		} // END language selection

		// skin & style selection
		$templates = $styleDefinition->getAllTemplates();
		//$this->ilias->getSkins();

		// preselect previous chosen skin/style otherwise default skin/style
		if (isset($_SESSION["error_post_vars"]["Fobject"]["skin_style"]))
		{
			$sknst = explode(":", $_SESSION["error_post_vars"]["Fobject"]["skin_style"]);

			$selected_style = $sknst[1];
			$selected_skin = $sknst[0];
		}
		else
		{
			$selected_style = $this->object->prefs["style"];
			$selected_skin = $this->object->skin;
		}

		foreach ($templates as $template)
		{
			// get styles for skin
			//$this->ilias->getStyles($template["id"]);
			$styleDef =& new ilStyleDefinition($template["id"]);
			$styleDef->startParsing();
			$styles = $styleDef->getStyles();

			foreach($styles as $style)
			{
				$this->tpl->setCurrentBlock("selectskin");

				if ($selected_skin == $template["id"] &&
					$selected_style == $style["id"])
				{
					$this->tpl->setVariable("SKINSELECTED", "selected=\"selected\"");
				}

				$this->tpl->setVariable("SKINVALUE", $template["id"].":".$style["id"]);
				$this->tpl->setVariable("SKINOPTION", $styleDef->getTemplateName()." / ".$style["name"]);
				$this->tpl->parseCurrentBlock();
			}
		} // END skin & style selection


		// time limit
		if (is_array($_SESSION["error_post_vars"]))
        {
            $time_limit_unlimited = $_SESSION["error_post_vars"]["time_limit"]["unlimited"];
        }
        else
        {
            $time_limit_unlimited = 1;
        }

        $time_limit_from = $_SESSION["error_post_vars"]["time_limit"]["from"] ?
            $this->__toUnix($_SESSION["error_post_vars"]["time_limit"]["from"]) :
            time();

        $time_limit_until = $_SESSION["error_post_vars"]["time_limit"]["until"] ?
            $this->__toUnix($_SESSION["error_post_vars"]["time_limit"]["until"]) :
            time();

		$this->lng->loadLanguageModule('crs');

		$this->tpl->setCurrentBlock("time_limit");
        $this->tpl->setVariable("TXT_TIME_LIMIT", $this->lng->txt("time_limit"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_UNLIMITED", $this->lng->txt("crs_unlimited"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_FROM", $this->lng->txt("crs_from"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_UNTIL", $this->lng->txt("crs_to"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_CLOCK", $this->lng->txt("clock"));
        $this->tpl->setVariable("TIME_LIMIT_UNLIMITED",ilUtil::formCheckbox($time_limit_unlimited,"time_limit[unlimited]",1));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_MINUTE",$this->__getDateSelect("minute","time_limit[from][minute]",
																					   date("i",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_HOUR",$this->__getDateSelect("hour","time_limit[from][hour]",
                                                                                     date("G",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_DAY",$this->__getDateSelect("day","time_limit[from][day]",
																					date("d",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_MONTH",$this->__getDateSelect("month","time_limit[from][month]",
																					  date("m",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_YEAR",$this->__getDateSelect("year","time_limit[from][year]",
																					 date("Y",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_MINUTE",$this->__getDateSelect("minute","time_limit[until][minute]",
																						date("i",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_HOUR",$this->__getDateSelect("hour","time_limit[until][hour]",
																					  date("G",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_DAY",$this->__getDateSelect("day","time_limit[until][day]",
																					 date("d",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_MONTH",$this->__getDateSelect("month","time_limit[until][month]",
																					   date("m",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_YEAR",$this->__getDateSelect("year","time_limit[until][year]",
																					  date("Y",$time_limit_until)));
		$this->tpl->parseCurrentBlock();


	}
	
	/**
	* set admin tabs
	* @access	public
	*
	function setAdminTabs()
	{
		global $rbacsystem;

		$tabs = array();
		$this->tpl->addBlockFile("TABS", "tabs", "tpl.tabs.html");
		
		if (isset($_POST["new_type"]) and $_POST["new_type"] == "usr")
		{
			$type = "usrf";
		}
		else
		{
			$type = $this->type;
		}
		$d = $this->objDefinition->getProperties($type);

		foreach ($d as $key => $row)
		{
			$tabs[] = array($row["lng"], $row["name"]);
		}

		// check for call_by_reference too to avoid hacking
		if (isset($_GET["obj_id"]) and $this->call_by_reference === false)
		{
			$object_link = "&obj_id=".$_GET["obj_id"];
		}

		foreach ($tabs as $row)
		{
			$i++;

			if ($row[1] == $_GET["cmd"])
			{
				$tabtype = "tabactive";
				$tab = $tabtype;
			}
			else
			{
				$tabtype = "tabinactive";
				$tab = "tab";
			}

			$show = true;

			// only check permissions for tabs if object is a permission object
			// TODO: automize checks by using objects.xml definitions!!
			if (true)
			//if ($this->call_by_reference)
			{
				// only show tab when the corresponding permission is granted
				switch ($row[1])
				{
					case 'view':
						if (!$rbacsystem->checkAccess('visible',$this->ref_id))
						{
							$show = false;
						}
						break;

					case 'edit':
						if (!$rbacsystem->checkAccess('write',$this->ref_id))
						{
							$show = false;
						}
						break;

					case 'perm':
						if (!$rbacsystem->checkAccess('edit_permission',$this->ref_id))
						{
							$show = false;
						}
						break;

					case 'trash':
						if (!$this->tree->getSavedNodeData($this->ref_id))
						{
							$show = false;
						}
						break;

					// user object only
					case 'roleassignment':
						if (!$rbacsystem->checkAccess('edit_roleassignment',$this->ref_id))
						{
							$show = false;
						}
						break;

					// role object only
					case 'userassignment':
						if (!$rbacsystem->checkAccess('edit_userassignment',$this->ref_id))
						{
							$show = false;
						}
						break;
				} //switch
			}

			if (!$show)
			{
				continue;
			}

			$this->tpl->setCurrentBlock("tab");
			$this->tpl->setVariable("TAB_TYPE", $tabtype);
			$this->tpl->setVariable("TAB_TYPE2", $tab);
			$this->tpl->setVariable("IMG_LEFT", ilUtil::getImagePath("eck_l.gif"));
			$this->tpl->setVariable("IMG_RIGHT", ilUtil::getImagePath("eck_r.gif"));
			$this->tpl->setVariable("TAB_LINK", $this->tab_target_script."?ref_id=".$_GET["ref_id"].$object_link."&cmd=".$row[1]);
			$this->tpl->setVariable("TAB_TEXT", $this->lng->txt($row[0]));
			$this->tpl->parseCurrentBlock();
		}
	}*/

	/**
	* display user edit form
	*
	* @access	public
	*/
	function editObject()
	{
		global $rbacsystem, $rbacreview, $rbacadmin, $styleDefinition;

		// deactivated:
		// or ($this->id != $_SESSION["AccountId"])
		if (!$rbacsystem->checkAccess('visible,read', $this->usrf_ref_id) and
			!$rbacsystem->checkAccess('cat_administrate_users',$this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_modify_user"),$this->ilias->error_obj->MESSAGE);
		}

		$data = array();
		$data["fields"] = array();
		$data["fields"]["login"] = $this->object->getLogin();
		$data["fields"]["passwd"] = "********";	// will not be saved
		$data["fields"]["passwd2"] = "********";	// will not be saved
		$data["fields"]["title"] = $this->object->getUTitle();
		$data["fields"]["gender"] = $this->object->getGender();
		$data["fields"]["firstname"] = $this->object->getFirstname();
		$data["fields"]["lastname"] = $this->object->getLastname();
		$data["fields"]["institution"] = $this->object->getInstitution();
		$data["fields"]["department"] = $this->object->getDepartment();
		$data["fields"]["street"] = $this->object->getStreet();
		$data["fields"]["city"] = $this->object->getCity();
		$data["fields"]["zipcode"] = $this->object->getZipcode();
		$data["fields"]["country"] = $this->object->getCountry();
		$data["fields"]["phone_office"] = $this->object->getPhoneOffice();
		$data["fields"]["phone_home"] = $this->object->getPhoneHome();
		$data["fields"]["phone_mobile"] = $this->object->getPhoneMobile();
		$data["fields"]["fax"] = $this->object->getFax();
		$data["fields"]["email"] = $this->object->getEmail();
		$data["fields"]["hobby"] = $this->object->getHobby();

		if (!count($user_online = ilUtil::getUsersOnline($this->object->getId())) == 1)
		{
			$user_is_online = false;
		}
		else
		{
			$user_is_online = true;

			// extract serialized role Ids from session data
			preg_match("/RoleId.*?;\}/",$user_online[$this->object->getId()]["data"],$matches);

			$active_roles = unserialize(substr($matches[0],7));

			// gather data for active roles
			$assigned_roles = $rbacreview->assignedRoles($this->object->getId());

			foreach ($assigned_roles as $key => $role)
			{
				$roleObj = $this->ilias->obj_factory->getInstanceByObjId($role);

				// fetch context path of role
				$rolf = $rbacreview->getFoldersAssignedToRole($role,true);

				// only list roles that are not set to status "deleted"
				if (count($rolf) > 0)
				{
					if (!$rbacreview->isDeleted($rolf[0]))
					{
						$path = "";

						if ($this->tree->isInTree($rolf[0]))
						{
							$tmpPath = $this->tree->getPathFull($rolf[0]);

							// count -1, to exclude the role folder itself
							for ($i = 0; $i < (count($tmpPath)-1); $i++)
							{
								if ($path != "")
								{
									$path .= " > ";
								}

								$path .= $tmpPath[$i]["title"];
							}
						}
						else
						{
							$path = "<b>Rolefolder ".$rolf[0]." not found in tree! (Role ".$role.")</b>";
						}

						if (in_array($role,$active_roles))
						{
							$data["active_role"][$role]["active"] = true;
						}

						$data["active_role"][$role]["title"] = $roleObj->getTitle();
						$data["active_role"][$role]["context"] = $path;

						unset($roleObj);
					}
				}
				else
				{
					$path = "<b>No role folder found for role ".$role."!</b>";
				}
			}
		}

		$this->getTemplateFile("edit","usr");

		// FILL SAVED VALUES IN CASE OF ERROR
		if (isset($_SESSION["error_post_vars"]["Fobject"]))
		{
			foreach ($_SESSION["error_post_vars"]["Fobject"] as $key => $val)
			{
				$str = $this->lng->txt($key);
				if ($key == "title")
				{
					$str = $this->lng->txt("person_title");
				}

				$this->tpl->setVariable("TXT_".strtoupper($key), $str);

				if ($key != "default_role" and $key != "language" and $key != "skin_style")
				{
					$this->tpl->setVariable(strtoupper($key), ilUtil::prepareFormOutput($val,true));
				}
			}

			// gender selection
			$gender = strtoupper($_SESSION["error_post_vars"]["Fobject"]["gender"]);

			if (!empty($gender))
			{
				$this->tpl->setVariable("BTN_GENDER_".$gender,"checked=\"checked\"");
			}
		}
		else
		{
			foreach ($data["fields"] as $key => $val)
			{
				$str = $this->lng->txt($key);
				if ($key == "title")
				{
					$str = $this->lng->txt("person_title");
				}
				$this->tpl->setVariable("TXT_".strtoupper($key), $str);

				$this->tpl->setVariable(strtoupper($key), ilUtil::prepareFormOutput($val));
				$this->tpl->parseCurrentBlock();
			}
			
			// gender selection
			$gender = strtoupper($data["fields"]["gender"]);
		
			if (!empty($gender))
			{
				$this->tpl->setVariable("BTN_GENDER_".$gender,"checked=\"checked\"");
			}
		}
		
		if (AUTH_CURRENT != AUTH_LOCAL)
		{
			$this->tpl->setVariable("OPTION_DISABLED", "\"disabled=disabled\"");
		}

		$obj_str = ($this->call_by_reference) ? "" : "&obj_id=".$this->obj_id;
		
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_HEADER", $this->lng->txt($this->object->getType()."_edit"));
		$this->tpl->setVariable("TXT_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->setVariable("TXT_SUBMIT", $this->lng->txt("save"));
		$this->tpl->setVariable("CMD_SUBMIT", "update");
		$this->tpl->setVariable("TARGET", $this->getTargetFrame("update"));
		$this->tpl->setVariable("TXT_REQUIRED_FLD", $this->lng->txt("required_field"));

		$this->tpl->setVariable("TXT_LOGIN_DATA", $this->lng->txt("login_data"));
		$this->tpl->setVariable("TXT_PERSONAL_DATA", $this->lng->txt("personal_data"));
		$this->tpl->setVariable("TXT_CONTACT_DATA", $this->lng->txt("contact_data"));
		$this->tpl->setVariable("TXT_SETTINGS", $this->lng->txt("settings"));
		$this->tpl->setVariable("TXT_PASSWD2", $this->lng->txt("retype_password"));
		$this->tpl->setVariable("TXT_LANGUAGE",$this->lng->txt("language"));
		$this->tpl->setVariable("TXT_SKIN_STYLE",$this->lng->txt("usr_skin_style"));
		$this->tpl->setVariable("TXT_GENDER_F",$this->lng->txt("gender_f"));
		$this->tpl->setVariable("TXT_GENDER_M",$this->lng->txt("gender_m"));

		// language selection
		$languages = $this->lng->getInstalledLanguages();

		// preselect previous chosen language otherwise default language
		$selected_lang = (isset($_SESSION["error_post_vars"]["Fobject"]["language"])) ? $_SESSION["error_post_vars"]["Fobject"]["language"] : $this->object->getLanguage();

		foreach ($languages as $lang_key)
		{
			$this->tpl->setCurrentBlock("language_selection");
			$this->tpl->setVariable("LANG", $this->lng->txt("lang_".$lang_key));
			$this->tpl->setVariable("LANGSHORT", $lang_key);

			if ($selected_lang == $lang_key)
			{
				$this->tpl->setVariable("SELECTED_LANG", "selected=\"selected\"");
			}

			$this->tpl->parseCurrentBlock();
		} // END language selection
		
		// skin & style selection
		//$this->ilias->getSkins();
		$templates = $styleDefinition->getAllTemplates();
		
		// preselect previous chosen skin/style otherwise default skin/style
		if (isset($_SESSION["error_post_vars"]["Fobject"]["skin_style"]))
		{
			$sknst = explode(":", $_SESSION["error_post_vars"]["Fobject"]["skin_style"]);
			
			$selected_style = $sknst[1];
			$selected_skin = $sknst[0];	
		}
		else
		{
			$selected_style = $this->object->prefs["style"];
			$selected_skin = $this->object->skin;	
		}
			
		foreach ($templates as $template)
		{
			// get styles for skin
			//$this->ilias->getStyles($skin["name"]);
			$styleDef =& new ilStyleDefinition($template["id"]);
			$styleDef->startParsing();
			$styles = $styleDef->getStyles();

			foreach ($styles as $style)
			{
				$this->tpl->setCurrentBlock("selectskin");

				if ($selected_skin == $template["id"] &&
					$selected_style == $style["id"])
				{
					$this->tpl->setVariable("SKINSELECTED", "selected=\"selected\"");
				}

				$this->tpl->setVariable("SKINVALUE", $template["id"].":".$style["id"]);
				$this->tpl->setVariable("SKINOPTION", $styleDef->getTemplateName()." / ".$style["name"]);
				$this->tpl->parseCurrentBlock();
			}
		} // END skin & style selection

		// inform user about changes option
		$this->tpl->setCurrentBlock("inform_user");


		if (true)
		{
			$this->tpl->setVariable("SEND_MAIL", " checked=\"checked\"");
		}

		$this->tpl->setVariable("TXT_INFORM_USER_MAIL", $this->lng->txt("inform_user_mail"));
		$this->tpl->parseCurrentBlock();

		$this->lng->loadLanguageModule('crs');

		$time_limit_unlimited = $_SESSION["error_post_vars"]["time_limit"]["unlimited"] ?
            $_SESSION["error_post_vars"]["time_limit"]["unlimited"] :
            $this->object->getTimeLimitUnlimited();
        $time_limit_from = $_SESSION["error_post_vars"]["time_limit"]["from"] ?
            $this->__toUnix($_SESSION["error_post_vars"]["time_limit"]["from"]) :
            $this->object->getTimeLimitFrom();

        $time_limit_until = $_SESSION["error_post_vars"]["time_limit"]["until"] ?
            $this->__toUnix($_SESSION["error_post_vars"]["time_limit"]["until"]) :
            $this->object->getTimeLimitUntil();

		$this->tpl->setCurrentBlock("time_limit");
        $this->tpl->setVariable("TXT_TIME_LIMIT", $this->lng->txt("time_limit"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_UNLIMITED", $this->lng->txt("crs_unlimited"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_FROM", $this->lng->txt("crs_from"));
        $this->tpl->setVariable("TXT_TIME_LIMIT_UNTIL", $this->lng->txt("crs_to"));

        $this->tpl->setVariable("TIME_LIMIT_UNLIMITED",ilUtil::formCheckbox($time_limit_unlimited,"time_limit[unlimited]",1));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_MINUTE",$this->__getDateSelect("minute","time_limit[from][minute]",
                                                                                     date("i",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_HOUR",$this->__getDateSelect("hour","time_limit[from][hour]",
                                                                                     date("G",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_DAY",$this->__getDateSelect("day","time_limit[from][day]",
                                                                                     date("d",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_MONTH",$this->__getDateSelect("month","time_limit[from][month]",
                                                                                       date("m",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_FROM_YEAR",$this->__getDateSelect("year","time_limit[from][year]",
                                                                                      date("Y",$time_limit_from)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_MINUTE",$this->__getDateSelect("minute","time_limit[until][minute]",
                                                                                     date("i",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_HOUR",$this->__getDateSelect("hour","time_limit[until][hour]",
                                                                                     date("G",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_DAY",$this->__getDateSelect("day","time_limit[until][day]",
                                                                                   date("d",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_MONTH",$this->__getDateSelect("month","time_limit[until][month]",
                                                                                     date("m",$time_limit_until)));
        $this->tpl->setVariable("SELECT_TIME_LIMIT_UNTIL_YEAR",$this->__getDateSelect("year","time_limit[until][year]",
                                                                                    date("Y",$time_limit_until)));
		$this->tpl->parseCurrentBlock();


		if ($user_is_online)
		{
			// BEGIN TABLE ROLES
			$this->tpl->setCurrentBlock("TABLE_ROLES");

			$counter = 0;

			foreach ($data["active_role"] as $role_id => $role)
			{
				++$counter;
				$css_row = ilUtil::switchColor($counter,"tblrow2","tblrow1");
				($role["active"]) ? $checked = "checked=\"checked\"" : $checked = "";

				$this->tpl->setVariable("ACTIVE_ROLE_CSS_ROW",$css_row);
				$this->tpl->setVariable("ROLECONTEXT",$role["context"]);
				$this->tpl->setVariable("ROLENAME",$role["title"]);
				$this->tpl->setVariable("CHECKBOX_ID", $role_id);
				$this->tpl->setVariable("CHECKED", $checked);
				$this->tpl->parseCurrentBlock();
			}
			// END TABLE ROLES

			// BEGIN ACTIVE ROLES
			$this->tpl->setCurrentBlock("ACTIVE_ROLE");
			$this->tpl->setVariable("ACTIVE_ROLE_FORMACTION","adm_object.php?cmd=activeRoleSave&ref_id=".
									$this->usrf_ref_id."&obj_id=".$this->obj_id);
			$this->tpl->setVariable("TXT_ACTIVE_ROLES",$this->lng->txt("active_roles"));
			$this->tpl->setVariable("TXT_ASSIGN",$this->lng->txt("change_active_assignment"));
			$this->tpl->parseCurrentBlock();
			// END ACTIVE ROLES
		}
	}

	/**
	* save user data
	* @access	public
	*/
	function saveObject()
	{
		global $rbacsystem, $rbacadmin;

		if (!$rbacsystem->checkAccess('create_user', $this->usrf_ref_id) and
			!$rbacsystem->checkAccess('cat_administrate_users',$this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_modify_user"),$this->ilias->error_obj->MESSAGE);
		}

		// check required fields
		if (empty($_POST["Fobject"]["firstname"]) or empty($_POST["Fobject"]["lastname"])
			or empty($_POST["Fobject"]["login"]) or empty($_POST["Fobject"]["email"])
			or empty($_POST["Fobject"]["passwd"]) or empty($_POST["Fobject"]["passwd2"])
			or empty($_POST["Fobject"]["gender"]))
		{
			$this->ilias->raiseError($this->lng->txt("fill_out_all_required_fields"),$this->ilias->error_obj->MESSAGE);
		}

		// validate login
		if (!ilUtil::isLogin($_POST["Fobject"]["login"]))
		{
			$this->ilias->raiseError($this->lng->txt("login_invalid"),$this->ilias->error_obj->MESSAGE);
		}

		// check loginname
		if (loginExists($_POST["Fobject"]["login"]))
		{
			$this->ilias->raiseError($this->lng->txt("login_exists"),$this->ilias->error_obj->MESSAGE);
		}

		// check passwords
		if ($_POST["Fobject"]["passwd"] != $_POST["Fobject"]["passwd2"])
		{
			$this->ilias->raiseError($this->lng->txt("passwd_not_match"),$this->ilias->error_obj->MESSAGE);
		}

		// validate password
		if (!ilUtil::isPassword($_POST["Fobject"]["passwd"]))
		{
			$this->ilias->raiseError($this->lng->txt("passwd_invalid"),$this->ilias->error_obj->MESSAGE);
		}

		// validate email
		if (!ilUtil::is_email($_POST["Fobject"]["email"]))
		{
			$this->ilias->raiseError($this->lng->txt("email_not_valid"),$this->ilias->error_obj->MESSAGE);
		}

		// validate time limit
        if ($_POST["time_limit"]["unlimited"] != 1 and
            ($this->__toUnix($_POST["time_limit"]["until"]) < $this->__toUnix($_POST["time_limit"]["from"])))
        {
            $this->ilias->raiseError($this->lng->txt("time_limit_not_valid"),$this->ilias->error_obj->MESSAGE);
        }


		// TODO: check if login or passwd already exists
		// TODO: check length of login and passwd

		// checks passed. save user
		$userObj = new ilObjUser();
		$userObj->assignData($_POST["Fobject"]);
		$userObj->setTitle($userObj->getFullname());
		$userObj->setDescription($userObj->getEmail());

		$userObj->setTimeLimitOwner($this->object->getRefId());
        $userObj->setTimeLimitUnlimited($_POST["time_limit"]["unlimited"]);
        $userObj->setTimeLimitFrom($this->__toUnix($_POST["time_limit"]["from"]));
        $userObj->setTimeLimitUntil($this->__toUnix($_POST["time_limit"]["until"]));
		
		$userObj->create();

		//$user->setId($userObj->getId());

		//insert user data in table user_data
		$userObj->saveAsNew();

		// setup user preferences
		$userObj->setLanguage($_POST["Fobject"]["language"]);

		//set user skin and style
		$sknst = explode(":", $_POST["Fobject"]["skin_style"]);

		if ($userObj->getPref("style") != $sknst[1] ||
			$userObj->getPref("skin") != $sknst[0])
		{
			$userObj->setPref("skin", $sknst[0]);
			$userObj->setPref("style", $sknst[1]);
		}

		$userObj->writePrefs();

		//set role entries
		$rbacadmin->assignUser($_POST["Fobject"]["default_role"],$userObj->getId(),true);

		/* moved the following to ObjUser->saveasNew
		// CREATE ENTRIES FOR MAIL BOX
		include_once ("classes/class.ilMailbox.php");
		$mbox = new ilMailbox($userObj->getId());
		$mbox->createDefaultFolder();

		include_once "classes/class.ilMailOptions.php";
		$mail_options = new ilMailOptions($userObj->getId());
		$mail_options->createMailOptionsEntry();

		// create personal bookmark folder tree
		include_once "classes/class.ilBookmarkFolder.php";
		$bmf = new ilBookmarkFolder(0, $userObj->getId());
		$bmf->createNewBookmarkTree();*/

		sendInfo($this->lng->txt("user_added"),true);

		
		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
			ilUtil::redirect($this->getReturnLocation("save","adm_object.php?ref_id=".$this->usrf_ref_id));
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}
	}

	/**
	* Does input checks and updates a user account if everything is fine.
	* @access	public
	*/
	function updateObject()
	{
		global $rbacsystem, $rbacadmin;

		// check write access
		if (!$rbacsystem->checkAccess('write', $this->usrf_ref_id) and
			!$rbacsystem->checkAccess('cat_administrate_users',$this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_modify_user"),$this->ilias->error_obj->MESSAGE);
		}

		foreach ($_POST["Fobject"] as $key => $val)
		{
			$_POST["Fobject"][$key] = ilUtil::stripSlashes($val);
		}

		if (AUTH_CURRENT == AUTH_LOCAL)
		{
			// check required fields
			if (empty($_POST["Fobject"]["firstname"]) or empty($_POST["Fobject"]["lastname"])
				or empty($_POST["Fobject"]["login"]) or empty($_POST["Fobject"]["email"])
				or empty($_POST["Fobject"]["passwd"]) or empty($_POST["Fobject"]["passwd2"])
				or empty($_POST["Fobject"]["gender"]))
			{
				$this->ilias->raiseError($this->lng->txt("fill_out_all_required_fields"),$this->ilias->error_obj->MESSAGE);
			}
		}
		else
		{
			if ((empty($_POST["Fobject"]["firstname"]) or empty($_POST["Fobject"]["lastname"])
				or empty($_POST["Fobject"]["email"]) or empty($_POST["Fobject"]["gender"])))
				{
					$this->ilias->raiseError($this->lng->txt("fill_out_all_required_fields"),$this->ilias->error_obj->MESSAGE);
				}
		}

		if (AUTH_CURRENT == AUTH_LOCAL)
		{
			// validate login
			if (!ilUtil::isLogin($_POST["Fobject"]["login"]))
			{
				$this->ilias->raiseError($this->lng->txt("login_invalid"),$this->ilias->error_obj->MESSAGE);
			}

			// check loginname
			if (loginExists($_POST["Fobject"]["login"],$this->id))
			{
				$this->ilias->raiseError($this->lng->txt("login_exists"),$this->ilias->error_obj->MESSAGE);
			}

			// check passwords
			if ($_POST["Fobject"]["passwd"] != $_POST["Fobject"]["passwd2"])
			{
				$this->ilias->raiseError($this->lng->txt("passwd_not_match"),$this->ilias->error_obj->MESSAGE);
			}

			// validate password
			if (!ilUtil::isPassword($_POST["Fobject"]["passwd"]))
			{
				$this->ilias->raiseError($this->lng->txt("passwd_invalid"),$this->ilias->error_obj->MESSAGE);
			}

			if ($_POST["Fobject"]["passwd"] != "********")
			{
				$this->object->resetPassword($_POST["Fobject"]["passwd"],$_POST["Fobject"]["passwd2"]);
			}
		}

		// validate email
		if (!ilUtil::is_email($_POST["Fobject"]["email"]))
		{
			$this->ilias->raiseError($this->lng->txt("email_not_valid"),$this->ilias->error_obj->MESSAGE);
		}
		
		$start = $this->__toUnix($_POST["time_limit"]["from"]);
		$end = $this->__toUnix($_POST["time_limit"]["until"]);
		
		// validate time limit
        if (!$_POST["time_limit"]["unlimited"]  and 
			( $start > $end))
        {
            $this->ilias->raiseError($this->lng->txt("time_limit_not_valid"),$this->ilias->error_obj->MESSAGE);
        }

		// time_limit modifications are only allowed for the childs of a user
		#if($start != $this->object->getTimeLimitFrom() or
		#   $end	  != $this->object->getTimeLimitUntil() or
		#   $_POST['time_limit']['unlimited'] != $this->object->getTimeLimitUnlimited())
		#{
		#	if(!$this->ilias->account->isChild($this->object->getId()))
		#	{
		#		$this->ilias->raiseError($this->lng->txt("time_limit_modification_not_allowed"),$this->ilias->error_obj->MESSAGE);
		#	}
		#}
				


		// TODO: check length of login and passwd

		// checks passed. save user
		$_POST['Fobject']['time_limit_owner'] = $this->object->getTimeLimitOwner();

		$_POST['Fobject']['time_limit_unlimited'] = (int) $_POST['time_limit']['unlimited'];
		$_POST['Fobject']['time_limit_from'] = $this->__toUnix($_POST['time_limit']['from']);
		$_POST['Fobject']['time_limit_until'] = $this->__toUnix($_POST['time_limit']['until']);

		if($_POST['Fobject']['time_limit_unlimited'] != $this->object->getTimeLimitUnlimited() or
		   $_POST['Fobject']['time_limit_from'] != $this->object->getTimeLimitFrom() or
		   $_POST['Fobject']['time_limit_until'] != $this->object->getTimeLimitUntil())
		{
			$_POST['Fobject']['time_limit_message'] = 0;
		}
		else
		{
			$_POST['Fobject']['time_limit_message'] = $this->object->getTimeLimitMessage();
		}
		$this->object->assignData($_POST["Fobject"]);

		if (AUTH_CURRENT == AUTH_LOCAL)
		{
			$this->object->updateLogin($_POST["Fobject"]["login"]);
		}
		
		$this->object->setTitle($this->object->getFullname());
		$this->object->setDescription($this->object->getEmail());
		$this->object->setLanguage($_POST["Fobject"]["language"]);
		
		//set user skin and style
		$sknst = explode(":", $_POST["Fobject"]["skin_style"]);
			
		if ($this->object->getPref("style") != $sknst[1] ||
			$this->object->getPref("skin") != $sknst[0])
		{
			$this->object->setPref("skin", $sknst[0]);
			$this->object->setPref("style", $sknst[1]);
		}

		$this->update = $this->object->update();
		//$rbacadmin->updateDefaultRole($_POST["Fobject"]["default_role"], $this->object->getId());

		// send email
		if ($_POST["send_mail"] == "y")
		{
			$this->lng->loadLanguageModule('crs');

			include_once "classes/class.ilFormatMail.php";

			$umail = new ilFormatMail($_SESSION["AccountId"]);

			// mail body
			$body = $this->lng->txt("login").": ".$this->object->getLogin()."\n\r".
				$this->lng->txt("passwd").": ".$_POST["Fobject"]["passwd"]."\n\r".
				$this->lng->txt("title").": ".$this->object->getTitle()."\n\r".
				$this->lng->txt("gender").": ".$this->object->getGender()."\n\r".
				$this->lng->txt("firstname").": ".$this->object->getFirstname()."\n\r".
				$this->lng->txt("lastname").": ".$this->object->getLastname()."\n\r".
				$this->lng->txt("institution").": ".$this->object->getInstitution()."\n\r".
				$this->lng->txt("department").": ".$this->object->getDepartment()."\n\r".
				$this->lng->txt("street").": ".$this->object->getStreet()."\n\r".
				$this->lng->txt("city").": ".$this->object->getCity()."\n\r".
				$this->lng->txt("zipcode").": ".$this->object->getZipcode()."\n\r".
				$this->lng->txt("country").": ".$this->object->getCountry()."\n\r".
				$this->lng->txt("phone_office").": ".$this->object->getPhoneOffice()."\n\r".
				$this->lng->txt("phone_home").": ".$this->object->getPhoneHome()."\n\r".
				$this->lng->txt("phone_mobile").": ".$this->object->getPhoneMobile()."\n\r".
				$this->lng->txt("fax").": ".$this->object->getFax()."\n\r".
				$this->lng->txt("email").": ".$this->object->getEmail()."\n\r".
				$this->lng->txt("hobby").": ".$this->object->getHobby()."\n\r".
				$this->lng->txt("default_role").": ".$_POST["Fobject"]["default_role"]."\n\r";

				if($this->object->getTimeLimitUnlimited())
				{
					$body .= $this->lng->txt('time_limit').": ".$this->lng->txt('crs_unlimited')."\n\r";
				}
				else
				{
					$body .= $this->lng->txt('time_limit').": ".$this->lng->txt('crs_from')." ".
						strftime('%Y-%m-%d %R',$this->object->getTimeLimitFrom())." ".
						$this->lng->txt('crs_to')." ".
						strftime('%Y-%m-%d %R',$this->object->getTimeLimitUntil())."\n\r";
				}


			if ($error_message = $umail->sendMail($this->object->getLogin(),"","",
												  $this->lng->txt("profile_changed"),$body,array(),array("normal")))
			{
				$msg = $this->lng->txt("saved_successfully")."<br/>".$error_message;
			}
			else
			{
				$msg = $this->lng->txt("saved_successfully")."<br/>".$this->lng->txt("mail_sent");
			}
		}
		else
		{
			$msg = $this->lng->txt("saved_successfully");
		}

		// feedback
		sendInfo($msg,true);

		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
			ilUtil::redirect("adm_object.php?ref_id=".$this->usrf_ref_id);
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}
	}


	/**
	* updates actives roles of user in session
	* DEPRECATED
	*
	* @access	public
	*/
	function activeRoleSaveObject()
	{
		global $rbacreview;

		$_POST["id"] = $_POST["id"] ? $_POST["id"] : array();

		// at least one active global role must be assigned to user
		$global_roles_all = $rbacreview->getGlobalRoles();
		$assigned_global_roles = array_intersect($_POST["id"],$global_roles_all);
		
		if (!count($_POST["id"]) or count($assigned_global_roles) < 1)
		{
			$this->ilias->raiseError($this->lng->txt("msg_min_one_active_role"),$this->ilias->error_obj->MESSAGE);
		}

		if ($this->object->getId() == $_SESSION["AccountId"])
		{
			$_SESSION["RoleId"] = $_POST["id"];
		}
		else
		{
			if (count($user_online = ilUtil::getUsersOnline($this->object->getId())) == 1)
			{
				//var_dump("<pre>",$user_online,$_POST["id"],"</pre>");exit;

				$roles = "RoleId|".serialize($_POST["id"]);
				$modified_data = preg_replace("/RoleId.*?;\}/",$roles,$user_online[$this->object->getId()]["data"]);

				$q = "UPDATE usr_session SET data='".$modified_data."' WHERE user_id = '".$this->object->getId()."'";
				$this->ilias->db->query($q);
			}
			else
			{
				// user went offline - do nothing
			}
		}

		sendInfo($this->lng->txt("msg_roleassignment_active_changed").".<br/>".$this->lng->txt("msg_roleassignment_active_changed_comment"),true);
		ilUtil::redirect("adm_object.php?ref_id=".$this->usrf_ref_id."&obj_id=".$this->obj_id."&cmd=edit");
	}

	/**
	* assign users to role
	*
	* @access	public
	*/
	function assignSaveObject()
	{
		global $rbacsystem, $rbacadmin, $rbacreview;

		if (!$rbacsystem->checkAccess("edit_roleassignment", $this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_assign_role_to_user"),$this->ilias->error_obj->MESSAGE);
		}

		$selected_roles = $_POST["role_id"] ? $_POST["role_id"] : array();
		$posted_roles = $_POST["role_id_ctrl"] ? $_POST["role_id_ctrl"] : array();
		
		// prevent unassignment of system role from system user
		if ($this->object->getId() == SYSTEM_USER_ID and in_array(SYSTEM_ROLE_ID, $posted_roles))
		{
			array_push($selected_roles,SYSTEM_ROLE_ID);
		}

		$global_roles_all = $rbacreview->getGlobalRoles();
		$assigned_roles_all = $rbacreview->assignedRoles($this->object->getId());
		$assigned_roles = array_intersect($assigned_roles_all,$posted_roles);
		$assigned_global_roles_all = array_intersect($assigned_roles_all,$global_roles_all);
		$assigned_global_roles = array_intersect($assigned_global_roles_all,$posted_roles);
		$posted_global_roles = array_intersect($selected_roles,$global_roles_all);
		
		if ((empty($selected_roles) and count($assigned_roles_all) == count($assigned_roles))
			 or (empty($posted_global_roles) and count($assigned_global_roles_all) == count($assigned_global_roles)))
		{
            //$this->ilias->raiseError($this->lng->txt("msg_min_one_role")."<br/>".$this->lng->txt("action_aborted"),$this->ilias->error_obj->MESSAGE);
            // workaround. sometimes jumps back to wrong page
            sendInfo($this->lng->txt("msg_min_one_role")."<br/>".$this->lng->txt("action_aborted"),true);
            $this->ctrl->redirect($this,'roleassignment');
		}

		foreach (array_diff($assigned_roles,$selected_roles) as $role)
		{
			$rbacadmin->deassignUser($role,$this->object->getId());
		}

		foreach (array_diff($selected_roles,$assigned_roles) as $role)
		{
			$rbacadmin->assignUser($role,$this->object->getId(),false);
		}
		
        include_once "./classes/class.ilObjRole.php";
        ilObjRole::_updateSessionRoles(array($this->object->getId()));

		// update object data entry (to update last modification date)
		$this->object->update();

		sendInfo($this->lng->txt("msg_roleassignment_changed"),true);

		if($this->ctrl->getTargetScript() == 'adm_object.php')
		{
            $this->ctrl->redirectByClass('ilobjusergui','roleassignment');
		}
		else
		{
			$this->ctrl->redirectByClass('ilobjcategorygui','listUsers');
		}

	}
	
	/**
	* display roleassignment panel
	*
	* @access	public
	*/
	function roleassignmentObject ()
	{
		global $rbacreview,$rbacsystem;

		if (!$rbacsystem->checkAccess("edit_roleassignment", $this->usrf_ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_assign_role_to_user"),$this->ilias->error_obj->MESSAGE);
		}
		

		$_SESSION['filtered_roles'] = isset($_POST['filter']) ? $_POST['filter'] : $_SESSION['filtered_roles'];

		$this->tpl->addBlockfile('ADM_CONTENT','adm_content','tpl.usr_role_assignment.html');

		if(true)
		{
			$this->tpl->setCurrentBlock("filter");
			$this->tpl->setVariable("FILTER_TXT_FILTER",$this->lng->txt('filter'));
			$this->tpl->setVariable("SELECT_FILTER",$this->__buildFilterSelect());
			$this->tpl->setVariable("FILTER_ACTION",$this->ctrl->getFormAction($this));
			$this->tpl->setVariable("FILTER_NAME",'roleassignment');
			$this->tpl->setVariable("FILTER_VALUE",$this->lng->txt('apply_filter'));
			$this->tpl->parseCurrentBlock();
		}
		
		// now get roles depending on filter settings
		$role_list = $rbacreview->getRolesByFilter($_SESSION["filtered_roles"],$this->object->getId());
		$assigned_roles = $rbacreview->assignedRoles($this->object->getId());

        $counter = 0;

		foreach ($role_list as $role)
		{
			// fetch context path of role
			$rolf = $rbacreview->getFoldersAssignedToRole($role["obj_id"],true);

			// only list roles that are not set to status "deleted"
			if ($rbacreview->isDeleted($rolf[0]))
			{
                continue;
            }
            
            // build context path
            $path = "";

			if ($this->tree->isInTree($rolf[0]))
			{
                if ($rolf[0] == ROLE_FOLDER_ID)
                {
                    $path = $this->lng->txt("global");
                }
                else
                {
				    $tmpPath = $this->tree->getPathFull($rolf[0]);

				    // count -1, to exclude the role folder itself
				    /*for ($i = 1; $i < (count($tmpPath)-1); $i++)
				    {
					    if ($path != "")
					    {
						    $path .= " > ";
					    }

					    $path .= $tmpPath[$i]["title"];
				    }*/
				
				    $path = $tmpPath[count($tmpPath)-2]["title"];
				}
			}
			else
			{
				$path = "<b>Rolefolder ".$rolf[0]." not found in tree! (Role ".$role["obj_id"].")</b>";
			}
			
			$disabled = false;
			
			// disable checkbox for system role for the system user
			if (($this->object->getId() == SYSTEM_USER_ID and $role["obj_id"] == SYSTEM_ROLE_ID)
				or (!in_array(SYSTEM_ROLE_ID,$_SESSION["RoleId"]) and $role["obj_id"] == SYSTEM_ROLE_ID))
			{
				$disabled = true;
			}
			
            $result_set[$counter][] = ilUtil::formCheckBox(in_array($role["obj_id"],$assigned_roles),"role_id[]",$role["obj_id"],$disabled)."<input type=\"hidden\" name=\"role_id_ctrl[]\" value=\"".$role["obj_id"]."\"/>";
            $result_set[$counter][] = "<a href=\"adm_object.php?ref_id=".$rolf[0]."&obj_id=".$role["obj_id"]."&cmd=perm\">".$role["title"]."</a>";
            $result_set[$counter][] = $role["description"];
		    $result_set[$counter][] = $path;

   			++$counter;
        }

		return $this->__showRolesTable($result_set);
    }
		
	/**
	* display public profile
	*
	* @param	string	$a_template_var			template variable where profile
	*											should be inserted
	* @param	string	$a_template_block_name	name of profile template block
	* @access	public
	*/
	function insertPublicProfile($a_template_var, $a_template_block_name, $a_additional = "")
	{
		$this->tpl->addBlockFile($a_template_var, $a_template_block_name, "tpl.usr_public_profile.html");
		$this->tpl->setCurrentBlock($a_template_block_name);

		// Get name of picture of user
		// TODO: the user is already the current user object !!
		$userObj = new ilObjUser($_GET["user"]);

		$this->tpl->setVariable("USR_PROFILE", $this->lng->txt("profile_of")." ".$this->object->getLogin());

		$this->tpl->setVariable("ROWCOL1", "tblrow1");
		$this->tpl->setVariable("ROWCOL2", "tblrow2");

		//if (usr_id == $_GET["user"])
		// Check from Database if value
		// of public_profile = "y" show user infomation
		if ($userObj->getPref("public_profile")=="y")
		{
			$this->tpl->setVariable("TXT_NAME",$this->lng->txt("name"));
			$this->tpl->setVariable("FIRSTNAME",$userObj->getFirstName());
			$this->tpl->setVariable("LASTNAME",$userObj->getLastName());
		}
		else
		{
			return;
			$this->tpl->setVariable("TXT_NAME",$this->lng->txt("name"));
			$this->tpl->setVariable("FIRSTNAME","N /");
			$this->tpl->setVariable("LASTNAME","A");
		}

		$webspace_dir = ilUtil::getWebspaceDir("output");
		if ($userObj->getPref("public_upload")=="y" && @is_file($webspace_dir."/usr_images/".$userObj->getPref("profile_image")))
		{
			//Getting the flexible path of image form ini file
			//$webspace_dir = ilUtil::getWebspaceDir("output");
			$this->tpl->setCurrentBlock("image");
			$this->tpl->setVariable("TXT_IMAGE",$this->lng->txt("image"));
			$this->tpl->setVariable("IMAGE_PATH", $webspace_dir."/usr_images/".$userObj->getPref("profile_image")."?dummy=".rand(1,999999));
			$this->tpl->parseCurrentBlock();
		}

		$val_arr = array("getInstitution" => "institution", "getDepartment" => "department",
			"getStreet" => "street",
			"getZipcode" => "zip", "getCity" => "city", "getCountry" => "country",
			"getPhoneOffice" => "phone_office", "getPhoneHome" => "phone_home",
			"getPhoneMobile" => "phone_mobile", "getFax" => "fax", "getEmail" => "email",
			"getHobby" => "hobby");

		foreach ($val_arr as $key => $value)
		{
			// if value "y" show information
			if ($userObj->getPref("public_".$value) == "y")
			{
				$this->tpl->setCurrentBlock("profile_data");
				$this->tpl->setVariable("TXT_DATA", $this->lng->txt($value));
				$this->tpl->setVariable("DATA", $userObj->$key());
				$this->tpl->parseCurrentBlock();
			}
		}

		if (is_array($a_additional))
		{
			foreach($a_additional as $key => $val)
			{
				$this->tpl->setCurrentBlock("profile_data");
				$this->tpl->setVariable("TXT_DATA", $key);
				$this->tpl->setVariable("DATA", $val);
				$this->tpl->parseCurrentBlock();
			}
		}

		$this->tpl->setCurrentBlock($a_template_block_name);
		$this->tpl->parseCurrentBlock();
	}


	function __getDateSelect($a_type,$a_varname,$a_selected)
    {
        switch($a_type)
        {
            case "minute":
                for($i=0;$i<=60;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "hour":
                for($i=0;$i<24;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "day":
                for($i=1;$i<32;$i++)
                {
                    $days[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$days,false,true);

            case "month":
                for($i=1;$i<13;$i++)
                {
                    $month[$i] = $i < 10 ? "0".$i : $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$month,false,true);

            case "year":
                for($i = date("Y",time());$i < date("Y",time()) + 3;++$i)
                {
                    $year[$i] = $i;
                }
                return ilUtil::formSelect($a_selected,$a_varname,$year,false,true);
        }
    }

	function __toUnix($a_time_arr)
    {
        return mktime($a_time_arr["hour"],
                      $a_time_arr["minute"],
                      $a_time_arr["second"],
                      $a_time_arr["month"],
                      $a_time_arr["day"],
                      $a_time_arr["year"]);
    }

	function __showRolesTable($a_result_set)
	{
        global $rbacsystem;

		$actions = array("assignSave"  => $this->lng->txt("change_assignment"));

        $tbl =& $this->__initTableGUI();
		$tpl =& $tbl->getTemplateObject();

		$tpl->setCurrentBlock("tbl_form_header");
		$tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$tpl->parseCurrentBlock();

		$tpl->setCurrentBlock("tbl_action_row");

			$tpl->setVariable("COLUMN_COUNTS",4);
			$tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));

            foreach ($actions as $name => $value)
			{
				$tpl->setCurrentBlock("tbl_action_btn");
				$tpl->setVariable("BTN_NAME",$name);
				$tpl->setVariable("BTN_VALUE",$value);
				$tpl->parseCurrentBlock();
			}

            $tpl->setVariable("TPLPATH",$this->tpl->tplPath);


		$this->ctrl->setParameter($this,"cmd","roleassignment");

		// title & header columns
		$tbl->setTitle($this->lng->txt("edit_roleassignment"),"icon_role_b.gif",$this->lng->txt("roles"));

		//user must be administrator
		$tbl->setHeaderNames(array("",$this->lng->txt("role"),$this->lng->txt("description"),$this->lng->txt("context")));
		$tbl->setHeaderVars(array("","title","description","context"),$this->ctrl->getParameterArray($this,"",false));
		$tbl->setColumnWidth(array("","30%","40%","30%"));

		$this->__setTableGUIBasicData($tbl,$a_result_set,"roleassignment");
		$tbl->render();
		$this->tpl->setVariable("ROLES_TABLE",$tbl->tpl->get());

		return true;
	}

	function &__initTableGUI()
	{
		include_once "class.ilTableGUI.php";

		return new ilTableGUI(0,false);
	}

	function __setTableGUIBasicData(&$tbl,&$result_set,$from = "")
	{
        switch($from)
		{
			default:
	           	$order = $_GET["sort_by"] ? $_GET["sort_by"] : "title";
				break;
		}

        //$tbl->enable("hits");
		$tbl->setOrderColumn($order);
		$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setOffset($_GET["offset"]);
		$tbl->setLimit($_GET["limit"]);
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		$tbl->setData($result_set);
	}

	function __unsetSessionVariables()
	{
        unset($_SESSION["filtered_roles"]);
	}

	function __buildFilterSelect()
	{
		$action[0] = $this->lng->txt('assigned_roles');
		$action[1] = $this->lng->txt('all_roles');
		$action[2] = $this->lng->txt('all_global_roles');
		$action[3] = $this->lng->txt('all_local_roles');
		$action[4] = $this->lng->txt('internal_local_roles_only');
		$action[5] = $this->lng->txt('non_internal_local_roles_only');

		return ilUtil::formSelect($_SESSION['filtered_roles'],"filter",$action,false,true);
	}

	function hitsperpageObject()
	{
        parent::hitsperpageObject();
        $this->roleassignmentObject();
	}
} // END class.ilObjUserGUI
?>
