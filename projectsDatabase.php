<?php

# Class to create a projects database system


require_once ('frontControllerApplication.php');
class projectsDatabase extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'div' => strtolower (__CLASS__),
			'database' => 'projects',
			'table' => 'projects',
			'useCamUniLookup' => true,
			'emailDomain' => 'cam.ac.uk',
			'administrators' => true,
			'useEditing' => true,
			'databaseStrictWhere' => true,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'home' => array (
				'description' => 'Projects listing',
				'url' => '',
				'tab' => 'Projects listing',
				'icon' => 'house',
			),
			'add' => array (
				'description' => 'Request a project',
				'url' => 'add.html',
				'tab' => 'Request a project',
				'icon' => 'add',
				'authentication' => true,
			),
			'general' => array (
				'description' => 'General ongoing work',
				'url' => 'general.html',
				'tab' => 'General ongoing work',
				'icon' => 'application_view_icons',
				'authentication' => true,
				'enableIf' => ($this->settings['generalHtml']),
			),
			'editing' => array (
				'description' => false,
				'url' => 'projects/',
				'tab' => 'Data editing',
				'icon' => 'pencil',
				'administrator' => true,
			),
			
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE `administrators` (
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE `projects` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '#' PRIMARY KEY,
			  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Project name',
			  `client` varchar(20) COLLATE utf8_unicode_ci NOT NULL COMMENT 'For',
			  `status` enum('proposed','specced','developing','completed','additional') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'proposed' COMMENT 'Status',
			  `startDate` date DEFAULT NULL COMMENT 'Start date',
			  `finishDate` date DEFAULT NULL COMMENT 'Finish date',
			  `description` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Description',
			  `progress` text COLLATE utf8_unicode_ci COMMENT 'Progress',
			  `daysEstimated` int(11) NULL DEFAULT NULL COMMENT 'Days estimated',
			  `daysSpent` int(11) NOT NULL COMMENT 'Days spent',
			  `url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'URL'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of projects';
			
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key (ignored)' PRIMARY KEY,
			  `generalHtml` text COLLATE utf8_unicode_ci COMMENT 'General ongoing work'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Settings';
		";
	}
	
	
	# Welcome screen
	public function home ()
	{
		# Start the HTML
		$html = '';
		
		# Get the list of projects
		if (!$projectsRaw = $this->databaseConnection->select ($this->settings['database'], $this->settings['table'], $conditions = "status != 'proposed'", $columns = array (), true, $orderBy = "FIELD(status, 'developing','additional','specced','proposed','completed'), id DESC")) {
			$html = "\n<p>There are no confirmed projects at present.</p>";
			echo $html;
			return;
		}
		
		# Attach status string to the key for CSS styling purposes
		$projects = array ();
		foreach ($projectsRaw as $id => $project) {
			$key = $id . ' ' . lcfirst ($project['status']);
			$projects[$key] = $project;
		}
		
		# Assemble fields
		foreach ($projects as $id => $project) {
			if ($this->userIsAdministrator) {
				$projects[$id]['id'] = "<a href=\"{$this->baseUrl}/projects/{$project['id']}/edit.html\"><strong>" . $projects[$id]['id'] . '</strong></a>';
			}
			$projects[$id]['name'] = "<a href=\"{$projects[$id]['url']}\" target=\"_blank\">" . htmlspecialchars ($projects[$id]['name']) . '</a>';
			unset ($projects[$id]['url']);
			$projects[$id]['status'] = ucfirst ($projects[$id]['status']);
			unset ($projects[$id]['client']);
			$projects[$id]['progress'] = nl2br (htmlspecialchars ($projects[$id]['progress']));
		}
		$allowHtml = array ('id', 'name', 'progress');
		
		# Find the earliest date
		$earliestDate = $this->databaseConnection->selectOneField ($this->settings['database'], $this->settings['table'], 'finishDate', 'finishDate IS NOT NULL', array (), false, $orderBy = 'finishDate', $limit = 1);
		
		# Create the HTML
		$html .= "\n<p>The table below shows a list of all confirmed projects" . ($earliestDate ? ' since ' . date ('F Y', strtotime ($earliestDate . ' 12:00:00')) : '') . '.</p>';
		if ($this->settings['generalHtml']) {
			$html .= "\n<p>This does not include <a href=\"{$this->baseUrl}/general.html\">general, ongoing work</a>.</p>";
		}
		$html .= "\n<p>You can <a href=\"{$this->baseUrl}/add.html\">request a project</a>.</p>";
		$tableHeadingSubstitutions = $this->databaseConnection->getHeadings ($this->settings['database'], $this->settings['table']);
		$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
		$html .= application::htmlTable ($projects, $tableHeadingSubstitutions, 'lines sortable" id="sortable', $keyAsFirstColumn = false, false, $allowHtml, false, false, $addRowKeyClasses = true);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Request project
	public function add ()
	{
		# Start the HTML
		$html = '';
		
		# Determine the recipients
		$recipients = array ();
		$recipients[] = $this->settings['administratorEmail'];		// Ensure this is first, i.e. the To: address
		foreach ($this->administrators as $administrator) {
			$recipients[] = $administrator['email'];
		}
		$recipients = array_unique ($recipients);
		
		# Create a new form
		$form = new form (array (
			'div' => 'lines form',
			'displayRestrictions' => false,
			'nullText' => '',
			'formCompleteText' => $this->tick . ' Thank you for your submission. We will be in touch shortly.',
			'autofocus' => true,
			'databaseConnection' => $this->databaseConnection,
			'picker' => true,
			'usersAutocomplete' => false,
			'rows' => 10,
			'cols' => 70,
		));
		$form->heading ('', "<p>Proposed projects can be submitted using this form.</p>");
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'includeOnly' => ($this->userIsAdministrator ? array () : array ('name', 'client', 'description')),
			'intelligence' => true,
			'size' => 70,
			'attributes' => $this->formDataBindingAttributes (),
		));
		#!# Reply-to field needs to be fully-qualified with e-mail domain
		$form->setOutputEmail ($recipients, $this->settings['administratorEmail'], $this->settings['applicationName'] . ': project submission', NULL, 'client');
		$form->setOutputScreen ();
		if ($result = $form->process ($html)) {
			
			# Set fixed fields
			$result['client'] = $this->user;
			
			# Insert into the database
			$this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $result);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Helper function to define the dataBinding attributes
	private function formDataBindingAttributes ()
	{
		# Define the properties
		$dataBindingAttributes = array (
			'client' => array ('editable' => ($this->userIsAdministrator), 'default' => $this->user, 'autocomplete' => $this->settings['usersAutocomplete'], 'autocompleteOptions' => array ('delay' => 0), 'description' => ($this->userIsAdministrator ? '(Field editable by admins only.)' : ''), ),
			'description' => array ('description' => 'Please also include a indication of desired timescale for delivery.'),
		);
		
		# Return the properties
		return $dataBindingAttributes;
	}
	
	
	# General ongoing work page
	public function general ()
	{
		# Start the HTML
		$html = '';
		
		# Show the general ongoing work HTML
		$html = $this->settings['generalHtml'];
		
		# Show the HTML
		echo $html;
	}
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function editing ($attributes = array (), $deny = false)
	{
		# Get the databinding attributes
		$dataBindingAttributes = $this->formDataBindingAttributes ();
		
		# Define general sinenomine settings
		$sinenomineExtraSettings = array (
			'datePicker' => true,
		);
		
		# Delegate to the standard function for editing
		echo $this->editingTable ($this->settings['table'], $dataBindingAttributes, 'ultimateform', false, $sinenomineExtraSettings);
	}
}

?>