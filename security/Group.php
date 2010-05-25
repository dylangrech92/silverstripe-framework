<?php
/**
 * A security group.
 * 
 * @package sapphire
 * @subpackage security
 */
class Group extends DataObject {
	
	static $db = array(
		"Title" => "Varchar",
		"Description" => "Text",
		"Code" => "Varchar",
		"Locked" => "Boolean",
		"Sort" => "Int",
		"IPRestrictions" => "Text",
		"HtmlEditorConfig" => "Varchar"
	);
	
	static $has_one = array(
		"Parent" => "Group",
	);
	
	static $has_many = array(
		"Permissions" => "Permission",
		"Groups" => "Group"
	);
	
	static $many_many = array(
		"Members" => "Member",
		"Roles" => "PermissionRole",
	);
	
	static $extensions = array(
		"Hierarchy",
	);
	
	function getAllChildren() {
		$doSet = new DataObjectSet();

		if ($children = DataObject::get('Group', 'ParentID = '.$this->ID)) {
			foreach($children as $child) {
				$doSet->push($child);
				$doSet->merge($child->getAllChildren());
			}
		}
		
		return $doSet;
	}
	
	/**
	 * Caution: Only call on instances, not through a singleton.
	 *
	 * @return FieldSet
	 */
	public function getCMSFields() {
		$fields = new FieldSet(
			new TabSet("Root",
				new Tab(_t('SecurityAdmin.MEMBERS', 'Members'),
					new TextField("Title", $this->fieldLabel('Title')),
					$memberList = new MemberTableField(
						$this,
						"Members",
						$this,
						null,
						false
					)
				),

				$permissionsTab = new Tab(_t('SecurityAdmin.PERMISSIONS', 'Permissions'),
					new LiteralField(
						"", 
						"<p>" . 
						_t('SecurityAdmin.ADVANCEDONLY',
							"This section is for advanced users only.
							See <a href=\"http://doc.silverstripe.com/doku.php?id=permissions:codes\" target=\"_blank\">this page</a>
							for more information."
						) . 
						"</p>"
					),
					new PermissionCheckboxSetField(
						'Permissions',
						singleton('Permission')->i18n_plural_name(),
						'Permission',
						'GroupID',
						$this
					)
				),

				new Tab(_t('Security.IPADDRESSES', 'IP Addresses'),
					new LiteralField("", _t('SecurityAdmin.IPADDRESSESHELP',"<p>You can restrict this group to a particular 
						IP address range (one range per line). <br />Ranges can be in any of the following forms: <br />
						203.96.152.12<br />
						203.96.152/24<br />
						203.96/16<br />
						203/8<br /><br />If you enter one or more IP address ranges in this box, then members will only get
						the rights of being in this group if they log on from one of the valid IP addresses.  It won't prevent
						people from logging in.  This is because the same user might have to log in to access parts of the
						system without IP address restrictions.")),
					new TextareaField("IPRestrictions", "IP Ranges", 10)
				)
			)
		);
		
		// Only add a dropdown for HTML editor configurations if more than one is available.
		// Otherwise Member->getHtmlEditorConfigForCMS() will default to the 'cms' configuration.
		$editorConfigMap = HtmlEditorConfig::get_available_configs_map();
		if(count($editorConfigMap) > 1) {
			$fields->addFieldToTab('Root.Permissions',
				new DropdownField(
					'HtmlEditorConfig', 
					'HTML Editor Configuration', 
					$editorConfigMap
				),
				'Permissions'
			);
		}

		if(!Permission::check('EDIT_PERMISSIONS')) {
			$fields->removeFieldFromTab('Root', 'Permissions');
			$fields->removeFieldFromTab('Root', 'IP Addresses');
		}

		if(Permission::check('APPLY_ROLES') && DataObject::get('PermissionRole')) { 
			$fields->addFieldToTab('Root.' . _t('SecurityAdmin.ROLES', 'Roles'), 
				new LiteralField( 
					"",  
					"<p>" .  
					_t('SecurityAdmin.ROLESDESCRIPTION', 
						"This section allows you to add roles to this group. Roles are logical groupings of permissions, which can be editied in the Roles tab" 
					) .  
					 "</p>" 
				) 
			);
			
			// Add roles (and disable all checkboxes for inherited roles)
			$allRoles = Permission::check('ADMIN') ? DataObject::get('PermissionRole') : DataObject::get('PermissionRole', 'OnlyAdminCanApply = 0');
			$groupRoles = $this->Roles();
			$inheritedRoles = new DataObjectSet();
			$ancestors = $this->getAncestors();
			foreach($ancestors as $ancestor) {
				$ancestorRoles = $ancestor->Roles();
				if($ancestorRoles) $inheritedRoles->merge($ancestorRoles);
			}
			$fields->findOrMakeTab('Root.Roles', 'Root.' . _t('SecurityAdmin.ROLES', 'Roles'));
			$fields->addFieldToTab(
				'Root.Roles',
				$rolesField = new CheckboxSetField('Roles', 'Roles', $allRoles)
			);
			$rolesField->setDefaultItems($inheritedRoles->column('ID'));
			$rolesField->setDisabledItems($inheritedRoles->column('ID'));
		} 
		
		$memberList->setController($this);
		$memberList->setPermissions(array('show', 'edit', 'delete', 'export', 'add', 'inlineadd'));
		$memberList->setParentClass('Group');
		$memberList->setPopupCaption(_t('SecurityAdmin.VIEWUSER', 'View User'));
		$memberList->setRelationAutoSetting(false);

		$fields->push($idField = new HiddenField("ID"));
		
		$this->extend('updateCMSFields', $fields);
		
		return $fields;
	}
	
	/**
	 *
	 * @param boolean $includerelations a boolean value to indicate if the labels returned include relation fields
	 * 
	 */
	function fieldLabels($includerelations = true) {
		$labels = parent::fieldLabels($includerelations);
		$labels['Title'] = _t('SecurityAdmin.GROUPNAME', 'Group name');
		$labels['Description'] = _t('Group.Description', 'Description');
		$labels['Code'] = _t('Group.Code', 'Group Code', PR_MEDIUM, 'Programmatical code identifying a group');
		$labels['Locked'] = _t('Group.Locked', 'Locked?', PR_MEDIUM, 'Group is locked in the security administration area');
		$labels['Sort'] = _t('Group.Sort', 'Sort Order');
		$labels['IPRestrictions'] = _t('Group.IPRestrictions', 'IP Address Restrictions');
		if($includerelations){
			$labels['Parent'] = _t('Group.Parent', 'Parent Group', PR_MEDIUM, 'One group has one parent group');
			$labels['Permissions'] = _t('Group.has_many_Permissions', 'Permissions', PR_MEDIUM, 'One group has many permissions');
			$labels['Members'] = _t('Group.many_many_Members', 'Members', PR_MEDIUM, 'One group has many members');
		}
		
		return $labels;
	}
	
	function populateDefaults() {
		parent::populateDefaults();
		
		if(!$this->Title) $this->Title = sprintf(
			_t('GROUP.NEWITEM',"New %s"), 
			singleton($this->class)->i18n_singular_name()
		);
	}
	
	/**
	 * Add a member to a group. This will create the group if the given 
	 * group code doesn't work.
	 *
	 * @param DataObject $member
	 * @param string $groupcode
	 */
	static function addToGroupByName($member, $groupcode) {
		$group = DataObject::get_one('Group', "\"Code\" = '" . Convert::raw2sql($groupcode). "'");
		if($group) {
			$member->Groups()->add($group);
		}
		else {
			$group = new Group();
			$group->Code = $groupcode;
			$group->Title = $groupcode;
			$group->write();
			
			$member->Groups()->add($group);
		}
	}
	
	/**
	 * Overloaded getter.
	 *
	 * @TODO Where is this used, why is this overloaded?
	 * 
	 * @param $limit string SQL
	 * @param $offset int
	 * @param $filter string SQL
	 * @param $sort string SQL
	 * @param $join string SQL
	 * @return ComponentSet
	 */
	public function Members($limit = "", $offset = "", $filter = "", $sort = "", $join = "") {
		$table = "Group_Members";
		if($filter) $filter = is_array($filter) ? $filter : array($filter);
		
		if( is_numeric( $limit ) ) {
			if( is_numeric( $offset ) )
				$limit = "$limit OFFSET $offset";
			else
				$limit = "$limit OFFSET 0";
		} else {
			$limit = "";
		}
		
		// Get all of groups that this group contains
		$groupFamily = implode(", ", $this->collateFamilyIDs());
		
		$filter[] = "\"$table\".\"GroupID\" IN ($groupFamily)";
		$join .= " INNER JOIN \"$table\" ON \"$table\".\"MemberID\" = \"Member\".\"ID\"" . Convert::raw2sql($join);
		
		$result = singleton("Member")->instance_get(
			$filter, 
			$sort,
			$join, 
			$limit,
			"ComponentSet" // datatype
			);
			
		if(!$result) $result = new ComponentSet();

		$result->setComponentInfo("many-to-many", $this, "Group", $table, "Member");
		foreach($result as $item) $item->GroupID = $this->ID;
		return $result;
	}
	
	public function map($filter = "", $sort = "", $blank="") {
		$ret = new SQLMap(singleton('Group')->extendedSQL($filter, $sort));
		if($blank){
			$blankGroup = new Group();
			$blankGroup->Title = $blank;
			$blankGroup->ID = 0;

			$ret->getItems()->shift($blankGroup);
		}
		return $ret;
	}
	
	/**
	 * Return a set of this record's "family" of IDs - the IDs of
	 * this record and all its descendants.
	 * @return array
	 */
	public function collateFamilyIDs() {
		$familyIDs = array();
		$chunkToAdd = array(array("ID" => $this->ID));
		
		while($chunkToAdd) {
			$idList = array();
			foreach($chunkToAdd as $item) {
				$idList[] = $item['ID'];
				$familyIDs[] = $item['ID'];
			}
			$idList = implode(',', $idList);
			
			// Get the children of *all* the groups identified in the previous chunk.
			// This minimises the number of SQL queries necessary			
			$sql = $this->extendedSQL("\"ParentID\" IN ($idList)", "");
			$dbResult = $sql->execute();
			$chunkToAdd = array();
			foreach($dbResult as $item) $chunkToAdd[] = $item;
		}
		
		return $familyIDs;
	}
	
	/**
	 * Returns an array of the IDs of this group and all its parents
	 */
	public function collateAncestorIDs() {
		$parent = $this;
		while(isset($parent)) {
			$items[] = $parent->ID;
			$parent = $parent->Parent;
		}
		return $items;
	}
	
	/**
	 * Override this so groups are ordered in the CMS
	 */
	public function stageChildren() {
		return DataObject::get('Group', "\"Group\".\"ParentID\" = " . (int)$this->ID . " AND \"Group\".\"ID\" != " . (int)$this->ID, '"Sort"');
	}
	
	public function getTreeTitle() {
	    if($this->hasMethod('alternateTreeTitle')) return $this->alternateTreeTitle();
		else return htmlspecialchars($this->Title, ENT_QUOTES);
	}
	
	/**
	 * Overloaded to ensure the code is always descent.
	 */
	public function setCode($val){
		$this->setField("Code",SiteTree::generateURLSegment($val));
	}
	
	function onBeforeWrite() {
		parent::onBeforeWrite();

		if(stripos($this->Code, _t('SecurityAdmin.NEWGROUPPREFIX','new-')) === 0) {
			$this->setCode($this->Title);
		}
	}
	
	function onAfterDelete() {
		parent::onAfterDelete();
		
		// Delete associated permissions
		$permissions = $this->Permissions();
		foreach ( $permissions as $permission ) {
			$permission->delete();
		}
	}
	
	/**
	 * Checks for permission-code CMS_ACCESS_SecurityAdmin.
	 * If the group has ADMIN permissions, it requires the user to have ADMIN permissions as well.
	 * 
	 * @param $member Member
	 * @return boolean
	 */
	public function canEdit($member = null) {
		if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) $member = Member::currentUser();
		
		// decorated access checks
		$results = $this->extend('canEdit', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
 		if(
			// either we have an ADMIN
			(bool)Permission::checkMember($member, "ADMIN")
			|| (
				// or a privileged CMS user and a group without ADMIN permissions.
				// without this check, a user would be able to add himself to an administrators group
				// with just access to the "Security" admin interface
				Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin") && 
				!DataObject::get("Permission", "GroupID = $this->ID AND Code = 'ADMIN'")
			)
		) {
			return true;
		}

		return false;
	}
	
	/**
	 * Checks for permission-code CMS_ACCESS_SecurityAdmin.
	 * 
	 * @param $member Member
	 * @return boolean
	 */
	public function canView($member = null) {
		if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) $member = Member::currentUser();
		
		// decorated access checks
		$results = $this->extend('canView', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
		// user needs access to CMS_ACCESS_SecurityAdmin
		if(Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin")) return true;
		
		return false;
	}
	
	public function canDelete($member = null) {
		if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) $member = Member::currentUser();
		
		// decorated access checks
		$results = $this->extend('canDelete', $member);
		if($results && is_array($results)) if(!min($results)) return false;
		
		return $this->canEdit($member);
	}

	/**
	 * Returns all of the children for the CMS Tree.
	 * Filters to only those groups that the current user can edit
	 */
	function AllChildrenIncludingDeleted() {
		$extInstance = $this->getExtensionInstance('Hierarchy');
		$extInstance->setOwner($this);
		$children = $extInstance->AllChildrenIncludingDeleted();
		$extInstance->clearOwner();
		
		$filteredChildren = new DataObjectSet();
		
		if($children) foreach($children as $child) {
			if($child->canView()) $filteredChildren->push($child);
		}
		
		return $filteredChildren;
	}
	
	/**
	 * Returns true if the given IP address is granted access to this group.
	 * For unrestricted groups, this always returns true.
	 */
	function allowedIPAddress($ip) {
		if(!$this->IPRestrictions) return true;
		if(!$ip) return false;
		
		$ipPatterns = explode("\n", $this->IPRestrictions);
		foreach($ipPatterns as $ipPattern) {
			$ipPattern = trim($ipPattern);
			if(preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)$/', $ipPattern, $matches)) {
				if($ip == $ipPattern) return true;
			} else if(preg_match('/^([0-9]+\.[0-9]+\.[0-9]+)\/24$/', $ipPattern, $matches)
					|| preg_match('/^([0-9]+\.[0-9]+)\/16$/', $ipPattern, $matches)
					|| preg_match('/^([0-9]+)\/8$/', $ipPattern, $matches)) {
				if(substr($ip, 0, strlen($matches[1])) == $matches[1]) return true;
			}
		}
		return false;
	}
	
	/**
	 * @return String
	 */
	function CMSTreeClasses($controller) {
		$classes = sprintf('class-%s', $this->class);

		if(!$this->canDelete())
			$classes .= " nodelete";

		if($controller->isCurrentPage($this))
			$classes .= " current";

		if(!$this->canEdit()) 
			$classes .= " disabled";
			
		$classes .= $this->markingClasses();

		return $classes;
	}
}
	
?>
