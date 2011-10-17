<?php
	
	/**
	 * @package sections_panel
	 */
	
	class Extension_Piwik extends Extension {
		/**
		 * Extension information.
		 */
		public function about() {
			return array(
				'name'			=> 'Piwik',
				'version'		=> '0.1',
				'release-date'	=> '2011-10-16',
				'author'		=> array(
					'name'			=> 'Henry Singleton',
					'website'		=> 'http://henrysingleton.com/',
					'email'			=> 'henry@henrysingleton.com'
				),
				'description'	=> 'Piwik dashboard integration with Symphony.'
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'DashboardPanelOptions',
					'callback'	=> 'dashboardPanelOptions'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'DashboardPanelRender',
					'callback'	=> 'dashboardPanelRender'
				),
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'DashboardPanelTypes',
					'callback'	=> 'dashboardPanelTypes'
				)
			);
		}
		
		/*============================
			Preferences
		============================*/
		public function appendPreferences($context) {
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(
				new XMLElement('legend', 'Piwik')
			);
			
			/* Location field */
			$label = Widget::Label('Location');
			$label->appendChild(Widget::Input(
				'settings[piwik][location]', Extension_Piwik::getLocation()
			));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('The URL for your Piwik installation, for example <code>http://example.com/piwik/</code>.'), array('class' => 'help')));

			/* Auth Token */
			$label = Widget::Label('Auth Token');
			$label->appendChild(Widget::Input(
				'settings[piwik][auth_token]', Extension_Piwik::getAuthToken()
			));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('You can get your token_auth value by clicking the \'API\' link at the top of your Piwik dashboard. Do not include the <code>&token_auth=</code> text.'), array('class' => 'help')));
			
			/* Site ID */
			$label = Widget::Label('Site ID');
			$label->appendChild(Widget::Input(
				'settings[piwik][site_id]', Extension_Piwik::getSiteId()
			));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('The ID of the piwik site you wish to access. By default the first site is <code>1</code>.'), array('class' => 'help')));

			
			$context['wrapper']->appendChild($group);
		}
		
		/*============================
			Utilities
		============================*/
		public static function getAuthToken() {
			return Symphony::Configuration()->get('auth_token', 'piwik');
		}
	
		public static function getLocation() {
			return Symphony::Configuration()->get('location', 'piwik');
		}
	
		public static function getSiteId() {
			return Symphony::Configuration()->get('site_id', 'piwik');
		}
	
		
		
		
		
		
		
		
		
		
		
		
		public function dashboardPanelOptions($context) {
			if ($context['type'] != 'piwik') return;
			
			$config = $context['existing_config'];
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(
				new XMLElement('legend', __('Piwik'))
			);
			
			$label = Widget::Label(__('Module'));
			$select = Widget::Select(
				'config[module]',
				$this->getModuleOptions(
					isset($config['module'])
						? $config['module']
						: null
				)
			);
			$label->appendChild($select);
			$fieldset->appendChild($label);
			
			$input = Widget::Input(
				'config[columns]',
				(
					isset($config['columns'])
						? $config['columns']
						: null
				)
			);
			$input->setAttribute('type', 'number');
			$input->setAttribute('size', '3');
			$label = Widget::Label(__(
				'Show the first %s columns in table.',
				array($input->generate())
			));
			$fieldset->appendChild($label);
			
			$input = Widget::Input(
				'config[entries]',
				(
					isset($config['entries'])
						? $config['entries']
						: null
				)
			);
			$input->setAttribute('type', 'number');
			$input->setAttribute('size', '3');
			$label = Widget::Label(__(
				'Show the first %s entries in table.',
				array($input->generate())
			));
			$fieldset->appendChild($label);
			
			$context['form'] = $fieldset;
		}
		
		public function dashboardPanelRender($context) {
			if ($context['type'] != 'piwik') return;
			
			$config = $context['config'];
			$panel = $context['panel'];
			$panel->setAttribute('class', 'panel-inner piwik');
			$em = new EntryManager();
			$sm = new SectionManager();
			
			// Get section information:
			$section = $sm->fetch($config['section']);
			$fields = $section->fetchVisibleColumns();
			$fields = array_splice(
				$fields, 0,
				(
					isset($config['columns'])
						? $config['columns']
						: 4
				)
			);
			$section_url = sprintf(
				'%s/publish/%s/',
				SYMPHONY_URL, $section->get('handle')
			);
			
			// Get entry information:
			$entries = $em->fetchByPage(1, $section->get('id'), 
				(
					isset($config['entries'])
						? $config['entries']
						: 4
				)
			);
			
			// Build table:
			$table = new XMLElement('table');
			$table_head = new XMLElement('thead');
			$table->appendChild($table_head);
			$table_body = new XMLElement('tbody');
			$table->appendChild($table_body);
			$panel->appendChild($table);
			
			// Add table headers:
			$row = new XMLElement('tr');
			$table_head->appendChild($row);
			
			foreach ($fields as $field) {
				$cell = new XMLElement('th');
				$cell->setValue($field->get('label'));
				$row->appendChild($cell);
			}
			
			// Add table body:
			foreach ($entries['records'] as $entry) {
				$row = new XMLElement('tr');
				$table_body->appendChild($row);
				$entry_url = $section_url . 'edit/' . $entry->get('id') . '/';
				
				foreach ($fields as $position => $field) {
					$data = $entry->getData($field->get('id'));
					$cell = new XMLElement('td');
					$row->appendChild($cell);
					
					$link = (
						$position === 0
							? Widget::Anchor(__('None'), $entry_url, $entry->get('id'), 'content')
							: null
					);
					$value = $field->prepareTableValue($data, $link, $entry->get('id'));
					
					if (isset($link)) {
						$value = $link->generate();
					}
					
					if ($value == 'None' || strlen($value) === 0) {
						$cell->setAttribute('class', 'inactive');
						$cell->setValue(__('None'));
					}
					
					else {
						$cell->setValue($value);
					}
				}
			}
		}
		
		public function dashboardPanelTypes($context) {
			$context['types']['Piwik'] = __('Piwik');
		}
		
		public function getModuleOptions($selected_module) {
			
			/*
			TODO: Get Module Files Here!
			*/
			
			foreach ($modules as $module) {
				$options[] = array(
					$module_filename,
					$module_filename == $selected_module,
					$module_filename /*pretty name */
				);
			}
			
			return $options;
		}
	}
	
?>