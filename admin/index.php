<?php
	/**
	 * © Copyright 2015 CFConsultancy, The Netherlands. This source code is the exclusive
	 * property of CFConsultancy. Any unauthorized adaptation, distribution, reproduction,
	 * decompilation or disclosure is strictly prohibited. Removing or altering this Copyright
	 * Notice is explicitly forbidden. Any violation will be prosecuted to the maximum extent
	 * permitted by law. http://www.cfconsultancy.nl
	 */

	 class FLATCMS {
		private $version = '1.0.4';
		private $logged_in = false;
		private $administrator = false;
		private $user = false;
		private $language = 'en-US';
		private $language_texts = array();
		private $allowed_extensions = array();
		private $editable_region_tags_start = array();
		private $editable_region_tags_end = array();

		/**
		 * Construct
		 */
		public function __construct(){
			# Start the session if not yet started
			if(session_id() === ''){
				session_start();
			}

			# Start Output Buffering
			@ob_start();

			# Register globals OFF hack
			unset($GLOBALS);

			# Magic Quotes OFF hack
			$_POST = $this->magicQuotesHack($_POST);
			$_GET = $this->magicQuotesHack($_GET);

			# Error reporting
			error_reporting(0);
			ini_set('display_errors','0');

			# Load configuration file
			if(!file_exists('./config.php') || filesize('./config.php') == 0){
				die('ERROR 2: Configuration file not found.');
			}else{
				require_once('./config.php');
			}

			# Backtrace limit
			ini_set('pcre.backtrack_limit','10000000');

			# Extra information in FLATCMS object
			$this->allowed_extensions = explode(',',strtolower(ALLOWED_EXTENSIONS));
			$this->editable_region_tags_start = explode(',',EDITABLE_REGION_TAGS_START);
			$this->editable_region_tags_end = explode(',',EDITABLE_REGION_TAGS_END);

			# Debug mode
			if(DEBUG_MODE == '1'){
				error_reporting(E_ALL);
				ini_set('display_errors','1');
			}

			# Determine subdir
			$website_url = str_replace(array('http://','https://'),'',WEBSITE_URL);
			if(substr_count($website_url,'/') <= 1){
				define('SUBDIR','');
			}else{
				define('SUBDIR',substr($website_url,strpos($website_url,'/')+1));
			}

			# Determine CMS directory
			$directory = str_replace('\\','/',getcwd());
			if(substr($directory,-1) != '/'){
				$directory .= '/';
			}
			$directory = substr($directory,(strrpos(substr($directory,0,-1),'/') !== false ? strrpos(substr($directory,0,-1),'/')+1 : 0),-1);
			define('CMS_DIRECTORY',$directory);

	    	# AJAX
	    	if(isset($_GET['ajax']) && $_GET['ajax'] == '1'){
				$ftp_conn = @ftp_connect($_GET['host'],$_GET['port'],5);
				$ftp_login = @ftp_login($ftp_conn,$_GET['username'],$_GET['password']);

				if(is_resource($ftp_conn) && $ftp_login == true){
					$ftp_root_directory = $this->ftpFindRootDirectory($ftp_conn);
				}else{
					$ftp_root_directory = '';
				}

				die('<input type="text" class="form-control ftp_field" name="ftp_root_directory" tabindex="12" value="' . $ftp_root_directory . '">');
	    	}

			# Load installation
			if(INSTALL){
				# Parse language files
				$this->loadLanguageFile();

				# Load installation
				$this->loadInstallation();

			# Load application
			}else{
				# Determine logged in
				$this->determineLoggedIn();

				# Parse language files
				$this->loadLanguageFile();

				# Login screen
				if(!$this->logged_in){
					$this->loadLogin();

				# Check the system configuration and load administration
				}elseif($this->checkSystem()){
					# Determine page
					if(!isset($_GET['page']) || $_GET['page'] == ''){
						$_GET['page'] = 'pages';
					}

					# Logout
					if($_GET['page'] == 'logout'){
						session_unset();

						# Redirect
						header('Location: ' . WEBSITE_URL);
						exit;
					}

					# OB
					ob_start();

					# Page add
					if($_GET['page'] == 'page_add'){
						$this->pageAdd();

					# Page delete
					}elseif($_GET['page'] == 'page_edit' && isset($_GET['filename']) && $_GET['filename'] != '' && strrpos($_GET['filename'],'../') === false && isset($_GET['region']) && $_GET['region'] != ''){
						$this->pageEdit($_GET['filename'],$_GET['region']);

					# Page delete
					}elseif($_GET['page'] == 'page_delete' && isset($_GET['filename']) && $_GET['filename'] != '' && strrpos($_GET['filename'],'../') === false && isset($_GET['hash']) && $_GET['hash'] == sha1(ADMINISTRATOR_PASSWORD . $_GET['filename'])){
						$this->pageDelete($_GET['filename']);

					# Editor CSS
					}elseif($_GET['page'] == 'editor_css' && isset($_GET['filename']) && $_GET['filename'] != '' && strrpos($_GET['filename'],'../') === false && isset($_GET['region']) && $_GET['region'] != ''){
						echo $this->getEditorCSS($_GET['filename'],$_GET['region']);
						return;

					# File manager
					}elseif($_GET['page'] == 'file_manager'){
						ob_end_clean();
						$this->loadFileManager();
						exit;
					# Internal link
					}elseif($_GET['page'] == 'internal_link'){
						ob_end_clean();
						$this->loadInternalLink();
						exit;
					# Configuration
					}elseif($_GET['page'] == 'configuration' && $this->logged_in && $this->administrator){
						$this->editConfiguration();

					# Pages
					}else{
						$this->pageOverview();
					}

					# /OB
					$content = ob_get_contents();
					ob_end_clean();

					# Determine menu-items
					if($this->user){
						$menu = '
						<div class="navbar-default sidebar" role="navigation">
						<div class="sidebar-nav navbar-collapse">
						<ul class="nav" id="side-menu">
							<li>
								<a href="./index.php?page=pages' . (isset($_GET['directory']) && $_GET['directory'] != '' && $_GET['directory'] != './' ? '&directory=' . urlencode($_GET['directory']) : (isset($_GET['filename']) && $_GET['filename'] != '' && dirname($_GET['filename']) != '.' ? '&directory=' . urlencode(dirname($_GET['filename']) . '/') : '')) . '" title="' . $this->getLanguage('pages') . '"><i class="fa fa-file-text-o fa-fw"></i>' . $this->getLanguage('pages') . '</a>
							</li>
						</ul>
						</div>
						<!-- /.sidebar-collapse -->
						</div>
						<!-- /.navbar-static-side -->
						</nav>';

					}elseif($this->administrator){
						$menu = '
						<div class="navbar-default sidebar" role="navigation">
						<div class="sidebar-nav navbar-collapse">
						<ul class="nav" id="side-menu">
							<li>
								<a href="./index.php?page=pages' . (isset($_GET['directory']) && $_GET['directory'] != '' && $_GET['directory'] != './' ? '&directory=' . urlencode($_GET['directory']) : (isset($_GET['filename']) && $_GET['filename'] != '' && dirname($_GET['filename']) != '.' ? '&directory=' . urlencode(dirname($_GET['filename']) . '/') : '')) . '" title="' . $this->getLanguage('pages') . '"><i class="fa fa-file-text-o fa-fw"></i> ' . $this->getLanguage('pages') . '</a>
							</li>
							<li>
								<a href="./index.php?page=configuration" title="' . $this->getLanguage('configuration') . '"><i class="fa fa-cog fa-fw"></i> ' . $this->getLanguage('configuration') . '</a>
							</li>
						</ul>
						</div>
						<!-- /.sidebar-collapse -->
						</div>
						<!-- /.navbar-static-side -->
						</nav>';
						}

					# Determine controls
					$controls = '
				        <!-- Navigation -->
				        <nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
				            <div class="navbar-header">
				                <button tabindex="-1" type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
				                    <span class="sr-only">Toggle navigation</span>
				                    <span class="icon-bar"></span>
				                    <span class="icon-bar"></span>
				                </button>
				                <a class="navbar-brand" href="index.php"><span class="cmsname"><i class="fa fa-leaf"></i>&nbsp;FLATCMS</span></a>
				            </div>
				            <!-- /.navbar-header -->
							<ul class="nav navbar-top-links navbar-right">
				                <!-- /.dropdown -->
				                <li class="dropdown">
				                    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
				                        <i class="fa fa-user fa-fw"></i>  <i class="fa fa-caret-down"></i>
				                    </a>
				                    <ul class="dropdown-menu dropdown-user">
				                        <li>
											<a href="' . WEBSITE_URL . '"><i class="fa fa-home fa-fw"></i> ' . strtolower($this->getLanguage('to_the_website')) . '</a>
				                        </li>
				                        <li>
											<a href="./manuals/Usersguide-FLATCMS-V1.10.pdf" target="_blank"><i class="fa fa-question-circle fa-fw"></i> ' . strtolower($this->getLanguage('manual')) . '</a>
				                        </li>
	                                    <li class="divider"></li>
										<li>
											<a href="./index.php?page=logout"><i class="fa fa-sign-out fa-fw"></i> ' . strtolower($this->getLanguage('logout')) . '</a>
				                        </li>
				                    </ul>
				                    <!-- /.dropdown-user -->
				                </li>
				                <!-- /.dropdown -->
				            </ul>';

					# Load template
					ob_start();
					require_once('./skins/index.php');
					$template = ob_get_contents();
					ob_end_clean();

					echo str_replace(array('[content]','[menu]','[controls]'),array($content,$menu,$controls),$template);
				}
			}
		}

		/**
		 * Destruct
		 */
		public function __destruct(){
			# Charset
			if(isset($_GET['page']) && $_GET['page'] == 'editor_css'){
				header('Content-type: text/css');
			}else{
				header('Content-type: text/html; charset=' . CHARSET);
			}

			# Output
			$content = ob_get_contents();
			ob_end_clean();
			echo $content;
		}


		/**
		 * Magic Quotes OFF hack
		 *
		 * @param array $array
		 * @return $array
		 */
		private function magicQuotesHack($array){
			if(get_magic_quotes_gpc()){
				foreach($array as $key => $value){
					if(is_array($value)){
						$array[$key] = $this->magicQuotesHack($value);
					}else{
						$array[$key] = stripslashes($value);
					}
				}
			}
			return $array;
		}


		/**
		 * Determine logged in
		 */
		private function determineLoggedIn(){
			# Is the user logged in?
			if(isset($_SESSION['cf_user']) && $_SESSION['cf_user'] == '1' && isset($_SESSION['cf-blowfish']) && $_SESSION['cf-blowfish'] == md5(BLOWFISH)) {
				$this->logged_in = true;
				$this->user = true;
			}

			# Is the administrator logged in?
			if(isset($_SESSION['cf_administrator']) && $_SESSION['cf_administrator'] == '1' && isset($_SESSION['cf-blowfish']) && $_SESSION['cf-blowfish'] == md5(BLOWFISH)) {
				$this->logged_in = true;
				$this->administrator = true;
			}
		}


		/**
		 * Load the language file (ini)
		 *
		 */
		private function loadLanguageFile(){
			# Determine which file
			if(INSTALL){
				if(isset($_GET['language']) && file_exists('./languages/' . $_GET['language'] . '.ini')){
					$language = $_GET['language'];
				}else{
					$language = 'en-US';
				}
			}elseif($this->logged_in && $this->user && file_exists('./languages/' . USER_LANGUAGE . '.ini')){
				$language = USER_LANGUAGE;
			}elseif($this->logged_in && $this->administrator && file_exists('./languages/' . ADMINISTRATOR_LANGUAGE . '.ini')){
				$language = ADMINISTRATOR_LANGUAGE;
			}elseif(file_exists('./languages/' . USER_LANGUAGE . '.ini')){
				$language = USER_LANGUAGE;
			}elseif(file_exists('./languages/' . ADMINISTRATOR_LANGUAGE . '.ini')){
				$language = ADMINISTRATOR_LANGUAGE;
			}else{
				$language = 'en-US';
			}
			$this->language = $language;

			# Parse language file
			$language = "\n" . trim(file_get_contents('./languages/' . $language . '.ini'));
			if(ini_get('magic_quotes_runtime')){
				$language = stripslashes($language);
			}
			preg_match_all('/\s+([^=]+)=([^\n]+)/ism',$language,$matches);

			foreach($matches[1] as $index => $name){
				$this->language_texts[trim($name)] = trim($matches[2][$index]);
			}
		}


		/**
		 * Get a language text
		 *
		 * @param string $name
		 * @param string $arg1 = null
		 * @param string $arg2 = null
		 * @param string $arg3 = null
		 * @return
		 */
		private function getLanguage($name, $arg1 = null, $arg2 = null, $arg3 = null){
			if($arg1 !== null && $arg1 != ''){
				$arg1 = '<strong>' . $arg1 . '</strong>';
			}
			if($arg2 !== null && $arg2 != ''){
				$arg2 = '<strong>' . $arg2 . '</strong>';
			}
			if($arg3 !== null && $arg3 != ''){
				$arg3 = '<strong>' . $arg3 . '</strong>';
			}

			if(array_key_exists($name,$this->language_texts)){
				return sprintf($this->language_texts[$name], $arg1, $arg2, $arg3);
			}else{
				return 'ERROR 1: Language text not found.';
			}
		}

		/**
		 * Load the login screen
		 *
		 */
		private function loadLogin(){
			# Handle login attempt
			if($_POST){
				if(isset($_POST['username']) && isset($_POST['password'])){
					$logged_in = false;

					# User level
					if($_POST['username'] == USER_USERNAME && sha1($_POST['password']) == USER_PASSWORD){
						$_SESSION["cf-blowfish"] = md5(BLOWFISH);
						$_SESSION['cf_user'] = '1';
						setcookie('cf_username',$_POST['username'],time()+3600*24*365);

						# Redirect
						header('Location: ./index.php');
						exit;

					# Administrator level
					}elseif($_POST['username'] == ADMINISTRATOR_USERNAME && sha1($_POST['password']) == ADMINISTRATOR_PASSWORD){
						$_SESSION["cf-blowfish"] = md5(BLOWFISH);
						$_SESSION['cf_administrator'] = '1';
						setcookie('cf_username',$_POST['username'],time()+3600*24*365);

						# Redirect
						header('Location: ./index.php');
						exit;
					}else{
						define('LOGIN_MESSAGE',$this->getLanguage('wrong_username_and_password_combination'));
					}
				}else{
					# Wrong combination
					define('LOGIN_MESSAGE',$this->getLanguage('wrong_username_and_password_combination'));
				}
			}else{
				define('LOGIN_MESSAGE','');
			}

			# Form
			$content = '<form role="form" name="login" action="' . WEBSITE_URL . CMS_DIRECTORY . '/index.php?page=login" method="post">
                            <fieldset>
                                <div class="form-group">
									<input name="username" tabindex="1" id="username" type="text" class="form-control" value="' . htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : (isset($_COOKIE['cf_username']) ? $_COOKIE['cf_username'] : $this->getLanguage('username'))) . '" >
                                </div>
                                <div class="form-group">
                                    <input name="password" tabindex="2" type="password" id="password" class="form-control" value="' . htmlspecialchars(!isset($_COOKIE['cf_username']) ? $this->getLanguage('password') : '') . '" onfocus="if(this.value == \'' . $this->getLanguage('password') . '\'){this.value=\'\';}">
                                </div>
                                <input name="submit" tabindex="3" class="btn btn-lg btn-success btn-block" type="submit" value="' . $this->getLanguage('login') . '">
                            </fieldset>
                        </form>';

			if(isset($_COOKIE['cf_username'])){
				$content .= '<script type="text/javascript">document.getElementById("password").focus();</script>';
			}

			include_once ('./classes/class.SecurityUtil.php');
			try {
				SecurityUtil :: throttleResource('./index.php', 10, 120, true); 		//this limits the number of times a user can access the login page to 5 every 2 minutes.
				//if code gets to here, no exception thrown, which means user has not exceeded allowed number of attempts, so process the login (or whatever request)
			}
			catch (Exception $e) {
				echo  'Too many login attempts. Maybe a bot ? Wait 2 minutes and try again !';
				exit;
			}

			# Load template
			ob_start();
			require_once('./skins/login.php');
			$template = ob_get_contents();
			ob_end_clean();

			echo str_replace('[content]',$content,$template);
		}


		/**
		 * Check the FTP details
		 *
		 * @param string $host
		 * @param integer $port
		 * @param string $passive_mode
		 * @param string $username
		 * @param string $password
		 * @param string $root_directory
		 */
		private function checkFTP($host,$port,$passive_mode,$username,$password,$root_directory){
			# Open FTP connection
			$handle = ftp_connect($host, $port, 5);

			# Error connecting
			if(!is_resource($handle)){
				echo '<p class="error">' . $this->getLanguage('opening_a_connection_with_on_port_failed',$host,$port) .  '</p>';
				return false;
			}

			# Login on FTP server
			$login = ftp_login($handle, $username, $password);

			# Error logging in
			if($login === false){
				echo '<p class="error">' . $this->getLanguage('login_with_username_failed_please_check_the_ftp_logins',$username) .  '</p>';
				return false;
			}

			# Passive mode
			if($passive_mode != ''){
				ftp_pasv($handle, (bool) $passive_mode);
			}

			# Check root directory
			$tmpfile = tmpfile();
			fseek($tmpfile,0);

			if(!ftp_fget($handle,$tmpfile,$root_directory . CMS_DIRECTORY . '/config.php',FTP_ASCII)){
				echo '<p class="error">' . $this->getLanguage('the_ftp_root_directory_is_incorrect') .  '</p>';
				return false;
			}
			fseek($tmpfile,0);

			if(preg_replace('/\s+/sm','',fread($tmpfile,filesize('./config.php'))) != preg_replace('/\s+/sm','',file_get_contents('./config.php'))){
				echo '<p class="error">' . $this->getLanguage('the_ftp_root_directory_is_incorrect') .  '</p>';
				return false;
			}

			# Close handle
			ftp_close($handle);
			return true;
		}


		/**
		 * Try to find the ftp root directory
		 *
		 * @param handler $ftp_conn
		 * @return string $ftp_root_directory
		 */
		public function ftpFindRootDirectory($ftp_conn){
			# CMS directory
			$cms_directory = dirname(__FILE__);
			if(strpos($cms_directory,'/') !== false){
				$cms_directory = substr($cms_directory,strrpos($cms_directory,'/')+1);
			}

			# Match root directory
			$directory = ftp_pwd($ftp_conn);
			$directory .= (substr($directory,-1) != '/' ? '/' : '');
			$tmp_file = tmpfile();
			@ftp_fget($ftp_conn,$tmp_file,$directory . 'config.php',FTP_ASCII);
			fseek($tmp_file,0);
			if(preg_replace('/\s+/sm','',@file_get_contents('./config.php')) == preg_replace('/\s+/sm','',fread($tmp_file,filesize('./config.php')))){
				return $directory;
			}

			# Match subdirectories
			$nlist = ftp_nlist($ftp_conn,ftp_pwd($ftp_conn));
			foreach($nlist as $directory){
				$rawlist = ftp_rawlist($ftp_conn,$directory,true);

				if($rawlist === false){
					return false;
				}

				foreach($rawlist as $line){
					# Trim the line
					$line = trim($line);

					# Skip empty lines
					if($line == ''){
						continue;
					}

					# Change current directory
					if(substr($line,-1) == ':'){
						$directory = substr($line,0,-1);
						$directory .= (substr($directory,-1) != '/' ? '/' : '');
						continue;
					}

					# File
					if(substr($line,0,1) == '-' && preg_match('/\s+[0-9:]+\s+[0-9:]+\s+([^$]+)$/i',$line,$matches) && $matches[1] == 'config.php'){
						$tmp_file = tmpfile();
						@ftp_fget($ftp_conn,$tmp_file,$directory . 'config.php',FTP_ASCII);
						fseek($tmp_file,0);
						if(preg_replace('/\s+/sm','',@file_get_contents('./config.php')) == preg_replace('/\s+/sm','',fread($tmp_file,filesize('./config.php')))){
							return substr($directory,0,0-strlen($cms_directory)-1);
						}
					}
				}
			}

			return false;
		}


		/**
		 * Load the installation procedure
		 *
		 */
		private function loadInstallation(){
			# Default values
			$server_name = (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != '' ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST']);
			$server_addr = $_SERVER['SERVER_ADDR'];
			$website_url = dirname('http' . ($_SERVER['SERVER_PORT'] == '443' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace('\\','/',$_SERVER['SCRIPT_NAME'])) . '/';
			$website_url = dirname(substr($website_url,0,-1)) . '/';
			$root_directory = dirname(str_replace('\\','/',dirname(__FILE__))) . '/';
			$user_username = 'user';
			$user_password = '';
			$administrator_username = 'admin';
			$administrator_password = '';
			$ftp_enabled = (bool) (function_exists('ftp_connect') && @ftp_connect($server_name,FTP_PORT,5));
			$ftp_host = 'localhost';
			$ftp_passive_mode = '';
			$ftp_port = '21';
			$ftp_username = '';
			$ftp_password = '';
			$ftp_root_directory = '';
			$content = '';
			$blowfish = '';

			# Charset
			$charset = null;
			if(file_exists('../index.php')){
				$html = file_get_contents('../index.php');
				if(stripos($html,'iso-8859-1') !== false){
					$charset = 'iso-8859-1';
				}elseif(stripos($html,'utf-8') !== false){
					$charset = 'utf-8';
				}
			}
			if($charset == null && file_exists('../index.html')){
				$html = file_get_contents('../index.html');
				if(stripos($html,'iso-8859-1') !== false){
					$charset = 'iso-8859-1';
				}elseif(stripos($html,'utf-8') !== false){
					$charset = 'utf-8';
				}
			}
			$html = '';
			if($charset == null){
				$charset = CHARSET;
			}

			# Header
			$content .= '<h1>' . $this->getLanguage('installation') . '</h1>';

			# Check if the domainname contains www.
			if(substr($server_name,0,4) != 'www.' && substr_count($server_name,'.') == 1){
				$content .= '<p class="error">' . $this->getLanguage('you_are_currently_installing_flatcms_on_are_you_sure_you_dont_want_to_install_flatcms_on',htmlspecialchars($server_name),'www.' . htmlspecialchars($server_name)) . '</p>';
			}

			# Save
			if($_POST && isset($_POST['install']) && $_POST['install'] == '1'){
				if(trim($_POST['user_username']) == '' || trim($_POST['user_password']) == '' || trim($_POST['administrator_username']) == '' || trim($_POST['administrator_password']) == '' || trim($_POST['website_url']) == '' || trim($_POST['root_directory']) == '' || ($_POST['ftp_enabled'] == '1' && (trim($_POST['ftp_host']) == '' || trim($_POST['ftp_port']) == '' || trim($_POST['ftp_username']) == '' || trim($_POST['ftp_password']) == '' || trim($_POST['ftp_root_directory']) == ''))){
					$content .= '<p class="error">' . $this->getLanguage('please_fill_in_the_required_fields') . '</p>';
				}else{
					# Check FTP details
					$ftp_error = false;
					if($_POST['ftp_enabled'] == '1'){
						# Make sure the root directory has a trailing slash
						$_POST['ftp_root_directory'] = $_POST['ftp_root_directory'] . (substr($_POST['ftp_root_directory'],-1) != '/' ? '/' : '');

						ob_start();
						$ftp_error = !$this->checkFTP($_POST['ftp_host'],$_POST['ftp_port'],$_POST['ftp_passive_mode'],$_POST['ftp_username'],$_POST['ftp_password'],$_POST['ftp_root_directory']);
						$content .= ob_get_contents();
						ob_end_clean();
					}else{
						$_POST['ftp_host'] = '';
						$_POST['ftp_port'] = '';
						$_POST['ftp_passive_mode'] = '';
						$_POST['ftp_username'] = '';
						$_POST['ftp_password'] = '';
						$_POST['ftp_root_directory'] = '';
					}

					# Write new config.php
					if(!$ftp_error){
						$config = file_get_contents('./config.php');
						if(ini_get('magic_quotes_runtime')){
							$config = stripslashes($config);
						}
						$exclude_directories = (EXCLUDE_DIRECTORIES == '' ? array() : explode(',',EXCLUDE_DIRECTORIES));
						$cms_directory = dirname(__FILE__);
						if(strpos($cms_directory,'/') !== false){
							$cms_directory = substr($cms_directory,strrpos($cms_directory,'/')+1);
						}
						$exclude_directories[] = $cms_directory;
						$exclude_directories = array_unique($exclude_directories);

						$replace = array(
										'INSTALL' => '0',
										'WEBSITE_URL' => $_POST['website_url'],
										'ROOT_DIRECTORY' => $_POST['root_directory'],
										'SERVER_ADDR' => $_SERVER['SERVER_ADDR'],
										'CHARSET' => $charset,
										'EXCLUDE_DIRECTORIES' => implode(',',$exclude_directories),

										'USER_USERNAME' => $_POST['user_username'],
										'USER_PASSWORD' => sha1($_POST['user_password']),

										'USER_LANGUAGE' => $this->language,
										'ADMINISTRATOR_USERNAME' => $_POST['administrator_username'],
										'ADMINISTRATOR_PASSWORD' => sha1($_POST['administrator_password']),
										'ADMINISTRATOR_LANGUAGE' => $this->language,

										'FTP_ENABLED' => $_POST['ftp_enabled'],
										'FTP_HOST' => $_POST['ftp_host'],
										'FTP_PORT' => $_POST['ftp_port'],
										'FTP_PASSIVE_MODE' => $_POST['ftp_passive_mode'],
										'FTP_USERNAME' => $_POST['ftp_username'],
										'FTP_PASSWORD' => $_POST['ftp_password'],
										'FTP_ROOT_DIRECTORY' => $_POST['ftp_root_directory'],
										'BLOWFISH' => substr(sha1(mt_rand()),0,30)
									);

						foreach($replace as $key => $value){
							$config = preg_replace('/define\(\'' . $key . '\',\'[^\']*\'\);/ism','define(\'' . $key . '\',\'' . str_replace("'","\'",$value) . '\');',$config);
						}

						if($_POST['ftp_enabled']){
							$result = $this->filePutContentsFTP('./' . CMS_DIRECTORY . '/config.php', $config, true, $_POST['ftp_host'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password'], $_POST['ftp_passive_mode'], $_POST['ftp_root_directory']);
						}else{
							$result = $this->filePutContentsPHP('./' . CMS_DIRECTORY . '/config.php',$config,$root_directory);
						}

						if($result){
							header('Location: ./index.php');
							exit;
						}else{
							$content .= '<p class="error">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>./' . CMS_DIRECTORY . '/config.php</strong>') . '</p>';
						}
					}
				}
			}

			# Form
			$content .= '<form name="install" class="form-horizontal form-install" role="form" action="./index.php?page=install&language=' . $this->language . '" method="post">
						 <input type="hidden" name="install" value="1" />
						 <div class="row">
                            <div class="col-lg-6">
							  <div class="form-group">
							  <h3>' . $this->getLanguage('user') . '</h3>
							    <label for="user_username" class="col-sm-2 control-label">' . $this->getLanguage('user') . '</label>
                                <div class="col-md-5">
							      <input type="text" class="form-control" id="user_username" name="user_username" tabindex="1" value="' . htmlspecialchars(isset($_POST['user_username']) ? $_POST['user_username'] : $user_username) . '">
                                </div>
							  </div>

							  <div class="form-group">
							    <label for="user_password" class="col-sm-2 control-label">' . $this->getLanguage('password') . '</label>
							    <div class="col-md-5">
							      <input type="password" class="form-control input_text" id="user_password" name="user_password" tabindex="2" value="' . htmlspecialchars(isset($_POST['user_password']) ? $_POST['user_password'] : $user_password) . '">
							    </div>
							  </div>

							  <div class="form-group">
                              <h3>' . $this->getLanguage('ftp') . '</h3>
                              	<label class="col-sm-2"></label>
                              	<div class="col-md-10">
							  	<label class="radio-inline">
                              		<input type="radio" name="ftp_enabled" id="ftp_enabled_yes" tabindex="3" value="1"' . (!function_exists('ftp_connect') ? ' disabled="disabled"' : '') . '' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '1') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '1') ? ' checked="checked"' : '') . '> ' . $this->getLanguage('yes') . '
							  	</label>
                              <label class="radio-inline">
							  	<input type="radio" name="ftp_enabled" value="0" tabindex="4"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' checked="checked"' : '') . '> ' . $this->getLanguage('no') . '
							  </label>
							  </div>
							  </div>

							  <div class="form-group">
							    <label for="ftp_host" class="col-sm-2 control-label">' . $this->getLanguage('host') . '</label>
                                <div class="col-md-8">
							      <input type="text" class="form-control ftp_field" id="ftp_host" name="ftp_host" tabindex="5" value="' . htmlspecialchars(isset($_POST['ftp_host']) ? $_POST['ftp_host'] : $ftp_host) . '"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>
                                </div>
							  </div>

							  <div class="form-group">
							    <label for="ftp_port" class="col-sm-2 control-label">' . $this->getLanguage('port') . '</label>
                                <div class="col-md-4">
							      <input type="text" class="form-control ftp_field" id="ftp_port" name="ftp_port" tabindex="6" value="' . htmlspecialchars(isset($_POST['ftp_port']) ? $_POST['ftp_port'] : $ftp_port) . '" style="width: 50px;"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . ' />
                                </div>
							  </div>

							  <div class="form-group">
                              <label class="col-sm-2">' . $this->getLanguage('passive_mode') . '</label>
                              <div class="col-md-10">
							  <label class="radio-inline">
                              	<input type="radio" name="ftp_passive_mode" class="ftp_field" tabindex="7" value="1"' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '1') || (!isset($_POST['ftp_passive_mode']) && $ftp_passive_mode == '1') ? ' checked="checked"' : '') . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>' . $this->getLanguage('yes') . '
							  </label>
                              <label class="radio-inline">
							  	<input type="radio" name="ftp_passive_mode" class="ftp_field" tabindex="8" value="0"' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '0') || (!isset($_POST['ftp_passive_mode']) && $ftp_passive_mode == '0') ? ' checked="checked"' : '') . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>' . $this->getLanguage('no') . '
							  </label>
                              <label class="radio-inline">
							  	<input type="radio" name="ftp_passive_mode" class="ftp_field" tabindex="9" value=""' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '') || (!isset($_POST['ftp_passive_mode']) && $ftp_passive_mode == '') ? ' checked="checked"' : '') . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>' . $this->getLanguage('default') . '
							  </label>
							  </div>
							  </div>

							  <div class="form-group">
							    <label for="ftp_username" class="col-sm-2 control-label">' . $this->getLanguage('user') . '</label>
                                <div class="col-md-5">
							      <input type="text" class="form-control ftp_field" id="ftp_username" id="ftp_username" name="ftp_username" tabindex="10" value="' . htmlspecialchars(isset($_POST['ftp_username']) ? $_POST['ftp_username'] : $ftp_username) . '"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>
                                </div>
							  </div>

							  <div class="form-group">
							    <label for="ftp_password" class="col-sm-2 control-label">' . $this->getLanguage('password') . '</label>
							    <div class="col-md-5">
							      <input type="password" class="form-control ftp_field" id="ftp_password" name="ftp_password" tabindex="11" value="' . htmlspecialchars(isset($_POST['ftp_password']) ? $_POST['ftp_password'] : $ftp_password) . '"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>
							    </div>
							  </div>

							  <div class="form-group">
							    <label for="ftp_root_directory" class="col-sm-2 control-label">' . $this->getLanguage('root_directory') . '</label>
                                <div class="col-md-8">
							      <input type="text" class="form-control ftp_field" id="ftp_root_directory" name="ftp_root_directory" tabindex="12" value="' . htmlspecialchars(isset($_POST['ftp_root_directory']) ? $_POST['ftp_root_directory'] : $ftp_root_directory) . '"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . '>
                                </div>
							  </div>
                            </div>
							<div class="col-lg-6">
                              <div class="form-group">
							  <h3>' . $this->getLanguage('administrator') . '</h3>
							    <label for="administrator_username" class="col-sm-3 control-label">' . $this->getLanguage('user') . '</label>
                                <div class="col-md-5">
							      <input type="text" class="form-control input_text" id="administrator_username" name="administrator_username" tabindex="13" value="' . htmlspecialchars(isset($_POST['administrator_username']) ? $_POST['administrator_username'] : $administrator_username) . '">
                                </div>
							  </div>

							  <div class="form-group">
							    <label for="administrator_password" class="col-sm-3 control-label">' . $this->getLanguage('password') . '</label>
							    <div class="col-md-5">
							      <input type="password" class="form-control input_text" id="administrator_password" name="administrator_password" tabindex="14" value="' . htmlspecialchars(isset($_POST['administrator_password']) ? $_POST['administrator_password'] : $administrator_password) . '">
							    </div>
							  </div>

							  <div class="form-group">
							  <h3>' . $this->getLanguage('website') . '</h3>
							    <label for="website_url" class="col-sm-3 control-label">' . $this->getLanguage('url') . '</label>
                                <div class="col-md-8">
							      <input type="text" class="form-control input_text" id="website_url" name="website_url" tabindex="15" value="' . htmlspecialchars(isset($_POST['website_url']) ? $_POST['website_url'] : $website_url) . '">
                                </div>
							  </div>

							  <div class="form-group">
							    <label for="root_directory" class="col-sm-3 control-label">' . $this->getLanguage('root_directory') . '</label>
                                <div class="col-md-8">
							      <input type="text" class="form-control" id="root_directory" name="root_directory" tabindex="16" value="' . htmlspecialchars(isset($_POST['root_directory']) ? $_POST['root_directory'] : $root_directory) . '">
                                </div>
							  </div>

							</div>

							<div class="clearfix"></div>
                            <div class="col-lg-12 text-center">
                            	<button tabindex="18" class="btn btn-primary" type="button" name="cancel" onclick="window.location.href = \'../\';">' . $this->getLanguage('cancel') . '</button>
								<button tabindex="17" class="btn btn-success" type="submit" name="submit">' . $this->getLanguage('install') . '</button>
                            </div>
						</div>
						</form>';


			# Controls
			$controls = '<div class="col-lg-2 pull-right"><select class="form-control" tabindex="-1" name="language">';

			$handle = opendir('./languages/');
			if(is_resource($handle)){
				while(($filename = readdir($handle)) !== false){
					if(substr($filename,-4) != '.ini'){
						continue;
					}

					$controls .= '<option value="' . substr($filename,0,-4) . '"' . ($this->language == substr($filename,0,-4) ? ' selected="selected"' : '') . '>' . (substr($this->getLanguage(substr($filename,0,-4)),0,5) != 'ERROR' ? $this->getLanguage(substr($filename,0,-4)) : substr($filename,0,-4)) . '</option>';
				}
			}
			$controls .= '</select></div>';

			if(file_exists('./manuals/Installationguide-FLATCMS-V1.00.pdf')){
				$controls .= '<a tabindex="-1" href="./manuals/Installationguide-FLATCMS-V1.00.pdf" target="_blank"><i class="fa fa-question-circle"></i> ' . strtolower($this->getLanguage('manual')) . '</a>';
			}else{
				$controls .= '';
			}

			# Load template
			ob_start();
			require_once('./skins/wide.php');
			$template = ob_get_contents();
			ob_end_clean();

			echo str_replace(array('[content]','[controls]'),array($content,$controls),$template);
		}


		/**
		 * Check all the paths in the config
		 * @return boolean $success
		 *
		 */
		private function checkSystem(){
			$website_url = dirname('http' . ($_SERVER['SERVER_PORT'] == '443' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace('\\','/',$_SERVER['SCRIPT_NAME'])) . '/';
			$website_url = dirname(substr($website_url,0,-1)) . '/';
			$root_directory = dirname(str_replace('\\','/',dirname(__FILE__))) . '/';

			# Did the URL's change?
			if((WEBSITE_URL != $website_url && !in_array($website_url,explode(',',TOLERATE_PATHS))) || (ROOT_DIRECTORY != $root_directory && !in_array($root_directory,explode(',',TOLERATE_PATHS)))){
				# OB
				ob_start();

				# Header
				echo '
				<div class="row">
					<div class="col-lg-12">
						<h1 class="page-header">' . $this->getLanguage('system_check') . '</h1>
					</div>
				</div>';

				# Server ip address changed
				if(SERVER_ADDR != $_SERVER['SERVER_ADDR']){
					echo '	<p>' . $this->getLanguage('the_ip_address_of_the_server_changed_please_reinstall_by_setting_the_value_install_in_the_config_php_to_1') . '</p>';
				}else{
					$ftp_enabled = (function_exists('ftp_connect') ? '1' : '0');

					# Paths
					$website_url = dirname('http' . ($_SERVER['SERVER_PORT'] == '443' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace('\\','/',$_SERVER['SCRIPT_NAME'])) . '/';
					$website_url = dirname(substr($website_url,0,-1)) . '/';
					$root_directory = dirname(str_replace('\\','/',dirname(__FILE__))) . '/';
					$ftp_root_directory = FTP_ROOT_DIRECTORY;

					if(substr(ROOT_DIRECTORY,0,strlen($root_directory)) == $root_directory && substr(ROOT_DIRECTORY,strlen($root_directory)) != ''){
						$ftp_root_directory = substr($ftp_root_directory,0,0-strlen(substr(ROOT_DIRECTORY,strlen($root_directory))));
					}
					if(substr($root_directory,0,strlen(ROOT_DIRECTORY)) == ROOT_DIRECTORY && substr($root_directory,strlen(ROOT_DIRECTORY)) != ''){
						$ftp_root_directory .= substr($root_directory,strlen(ROOT_DIRECTORY));
					}
					if(strlen(FTP_ROOT_DIRECTORY) > 1 && stripos(ROOT_DIRECTORY,FTP_ROOT_DIRECTORY) !== false && substr(ROOT_DIRECTORY,0,strpos(ROOT_DIRECTORY,FTP_ROOT_DIRECTORY)+1) != '' && substr(ROOT_DIRECTORY,0,strpos(ROOT_DIRECTORY,FTP_ROOT_DIRECTORY)+1) == substr($root_directory,0,strpos(ROOT_DIRECTORY,FTP_ROOT_DIRECTORY)+1)){
						$ftp_root_directory = substr($root_directory,strpos(ROOT_DIRECTORY,FTP_ROOT_DIRECTORY));
					}

					# Save
					if($_POST){
						# Get original config
						$content = file_get_contents('./config.php');
						if(ini_get('magic_quotes_runtime')){
							$content = stripslashes($content);
						}

						# Keep using current values
						if($_POST['submit'] == $this->getLanguage('keep_using_current_values')){
							# Change the config.php
							if(trim(TOLERATE_PATHS) == ''){
								$tolerate_paths = array();
							}else{
								$tolerate_paths = explode(',',TOLERATE_PATHS);
							}
							if(WEBSITE_URL != $website_url){
								$tolerate_paths[] = $website_url;
							}
							if(ROOT_DIRECTORY != $root_directory){
								$tolerate_paths[] = $root_directory;
							}
							$tolerate_paths = array_unique($tolerate_paths);
							$content = preg_replace('/(define\(.?TOLERATE_PATHS.?,.?)([^"\']*)(.?\);)/ism','$1' . implode(',',$tolerate_paths) . '$3',$content);

							# Save config.php
							if(filePutContents(CMS_DIRECTORY . '/config.php',$content)){
								# Redirect
								header('Location: ./index.php');
								exit;
							}else{
								echo '<p class="error">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>config.php</strong>') . '</p>';
							}
						}else{
							# Paths
							if(substr($_POST['root_directory'],-1) != '/'){
								$_POST['root_directory'] .= '/';
							}
							if(substr($_POST['website_url'],-1) != '/'){
								$_POST['website_url'] .= '/';
							}
							if(substr($_POST['ftp_root_directory'],-1) != '/'){
								$_POST['ftp_root_directory'] .= '/';
							}

							# Change the config.php
							$content = preg_replace('/(define\(.?ROOT_DIRECTORY.?,.?)([^"\']*)(.?\);)/ism','$1' . $_POST['root_directory'] . '$3',$content);
							$content = preg_replace('/(define\(.?WEBSITE_URL.?,.?)([^"\']*)(.?\);)/ism','$1' . $_POST['website_url'] . '$3',$content);
							$content = preg_replace('/(define\(.?FTP_ROOT_DIRECTORY.?,.?)([^"\']*)(.?\);)/ism','$1' . $_POST['ftp_root_directory'] . '$3',$content);

							# Save config.php
							if($this->filePutContents(CMS_DIRECTORY . '/config.php',$content,true,$_POST['ftp_root_directory'],$_POST['root_directory'])){
								# Redirect
								header('Location: ./index.php');
								exit;
							}else{
								echo '<p class="error">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>config.php</strong>') . '</p>';
							}
						}
					}

					# Explanation
					echo '	<p>' . $this->getLanguage('the_system_has_detected_a_change_in_the_configuration') . ' ' . $this->getLanguage('you_probably_moved_the_cms_to_another_directory') . '</p>';
					echo '	<p class="error">' . $this->getLanguage('please_check_the_following_paths') . '</p>';

					# Form
					echo '	<form name="system" action="./index.php" method="post">
					<table style="width: 100%">
						<tr>
							<th style="width: 25%;">' . $this->getLanguage('website') . '</th>
							<th style="width: 25%;">' . $this->getLanguage('current_value') . '</th>
							<th style="width: 50%;">' . $this->getLanguage('our_suggestion') . '</th>
						</tr>
						<tr>
							<td>' . $this->getLanguage('url') . '</td>
							<td>' . WEBSITE_URL . '</td>
							<td><input type="text" class="form-control" id="website_url" name="website_url" value="' . htmlspecialchars($_POST ? $_POST['website_url'] : $website_url) . '"><br></td>
						</tr>
						<tr>
							<td>' . $this->getLanguage('root_directory') . '</td>
							<td>' . ROOT_DIRECTORY . '</td>
							<td><input type="text" class="form-control" name="root_directory" id="root_directory" value="' . htmlspecialchars($_POST ? $_POST['root_directory'] : $root_directory) . '"></td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<th colspan="3">' . $this->getLanguage('ftp') . '</th>
						</tr>
						<tr>
							<td>' . $this->getLanguage('root_directory') . '</td>
							<td>' . FTP_ROOT_DIRECTORY . '</td>
							<td><input type="text" class="form-control" name="ftp_root_directory" id="ftp_root_directory" value="' . htmlspecialchars($_POST ? $_POST['ftp_root_directory'] : $ftp_root_directory) . '"' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && FTP_ENABLED == '0') ? ' disabled' : '') . '></td>
						</tr>
						<tr>
							<td colspan="3">&nbsp;</td>
						</tr>
						<tr>
							<td>&nbsp;</td>
							<td><input type="submit" name="submit" class="btn btn-primary" value="' . $this->getLanguage('keep_using_current_values') . '" /></td>
							<td><input type="submit" name="submit" class="btn btn-success" value="' . $this->getLanguage('use_new_values') . '" /></td>
						</tr>
					</table>
					</form>';
					}

				# /OB
				$content = ob_get_contents();
				ob_end_clean();

				# Load template
				ob_start();
				require_once('./skins/wide.php');
				$template = ob_get_contents();
				ob_end_clean();

                $controls = '';
				# Controls

				echo str_replace(array('[content]','[controls]'),array($content,$controls),$template);

				return false;
			}else{
				return true;
			}
		}


		/**
		 * Get (title and meta) information from a page
		 *
		 * @param string $filename
		 * @return array $data
		 */
		private function getPageInformation($filename){
			# Check the file
			if(!file_exists($filename) || filesize($filename) == 0 || !($content = file_get_contents($filename))){
				return array('title' => '', 'keywords' => '', 'description' => '');
			}
			if(ini_get('magic_quotes_runtime')){
				$content = stripslashes($content);
			}

			# Title
			if(preg_match('#<title>([^<]*)</title>#ism',$content,$matches)){
				$title = $matches[1];
			}else{
				$title = null;
			}

			# Keywords
			$keywords = null;
			if(preg_match_all('#<meta([^<]+)>#ism',$content,$matches)){
				foreach($matches[1] as $match){
					if(preg_match('#name\s*=\s*("|\'){1}keywords("|\'){1}#ism',$match,$matches_n) && preg_match('#content\s*=\s*("|\'){1}([^"\']*)("|\'){1}#ism',$match,$matches_v)){
						$keywords = $matches_v[2];
					}
				}
			}

			# Description
			$description = null;
			if(preg_match_all('#<meta([^<]+)>#ism',$content,$matches)){
				foreach($matches[1] as $match){
					if(preg_match('#name\s*=\s*("|\'){1}description("|\'){1}#ism',$match,$matches_n) && preg_match('#content\s*=\s*("|\'){1}([^"\']*)("|\'){1}#ism',$match,$matches_v)){
						$description = $matches_v[2];
					}
				}
			}

			return array('title' => $title, 'keywords' => $keywords, 'description' => $description);
		}


		/**
		 * Write to a file. With FTP if possible, otherwise through PHP
		 *
		 * @param string $filename
		 * @param string $content
		 * @param boolean $ascii = true
		 * @param string $ftp_root_directory = null
		 * @param string $root_directory = null
		 * @param boolean $force_php = false
		 */
		private function filePutContents($filename, $content, $ascii = true, $ftp_root_directory = null, $root_directory = null, $force_php = false){
			# Paths
			if($ftp_root_directory == null){
				$ftp_root_directory = FTP_ROOT_DIRECTORY;
			}
			if($root_directory == null){
				$root_directory = ROOT_DIRECTORY;
			}

			# Use FTP
			if(FTP_ENABLED && function_exists('ftp_connect') && !$force_php){
				return $this->filePutContentsFTP($filename, $content, $ascii, FTP_HOST, FTP_PORT, FTP_USERNAME, FTP_PASSWORD, FTP_PASSIVE_MODE, $ftp_root_directory);
			# Use PHP
			}else{
				return $this->filePutContentsPHP($filename, $content, $root_directory);
			}
		}


		/**
		 * Write to a file with PHP
		 *
		 * @param string $filename
		 * @param string $content
		 * @param string $root_directory
		 * @return boolean
		 */
		private function filePutContentsPHP($filename, $content, $root_directory){
			# Write content
			$handle = fopen($root_directory . $filename,"w+");

			if(!$handle){
				return false;
			}else{
				fwrite($handle,$content);
				fclose($handle);
				return true;
			}
		}


		/**
		 * Write to a file with FTP
		 *
		 * @param string $filename
		 * @param string $content
		 * @param boolean $ascii
		 * @param string $ftp_host
		 * @param integer $ftp_port
		 * @param string $ftp_username
		 * @param string $ftp_password
		 * @param string $ftp_passive_mode
		 * @param string $ftp_root_directory
		 * @return boolean
		 */
		private function filePutContentsFTP($filename, $content, $ascii, $ftp_host, $ftp_port, $ftp_username, $ftp_password, $ftp_passive_mode, $ftp_root_directory){
			# Open FTP connection
			$handle = ftp_connect($ftp_host, $ftp_port, 5);

			# Error connecting
			if(!is_resource($handle)){
				echo '<p class="error">' . $this->getLanguage('opening_a_connection_with_on_port_failed',$ftp_host,$ftp_port) .  '</p>';
				return false;
			}

			# Login on FTP server
			$login = ftp_login($handle, $ftp_username, $ftp_password);

			# Error logging in
			if($login === false){
				echo '<p class="error">' . $this->getLanguage('login_with_username_failed_please_check_the_ftp_logins',$ftp_username) .  '</p>';
				return false;
			}

			# Passive mode
			if($ftp_passive_mode != ''){
				ftp_pasv($handle, (bool) $ftp_passive_mode);
			}

			# Windows problems? Fix for extra empty lines when content contains \r\n.
			if($ascii){
				$content = str_replace(array("\r\n","\r"),"\n",$content);
			}

			# TMP File
			$tmp_handle = tmpfile();
			fwrite($tmp_handle, $content);
			fseek($tmp_handle,0);

			# Write tmp file to FTP
			$result = (ftp_fput($handle, $ftp_root_directory . $filename, $tmp_handle, ($ascii ? FTP_ASCII : FTP_BINARY)) == 1 ? 1 : 0);

			# Close tmp-file-handle
			fclose($tmp_handle);

			return $result;
		}


		/**
		 * Encode the HTML charakters to use in the editor
		 *
		 * @param string $sHTML
		 * @return $sHTML
		 */
		private function encodeHTML($sHTML){
			$sHTML = str_replace("&","&amp;",$sHTML);
			$sHTML = str_replace("<","&lt;",$sHTML);
			$sHTML = str_replace(">","&gt;",$sHTML);
			$sHTML = str_replace("[","&#91;",$sHTML);
			$sHTML = str_replace("]","&#93;",$sHTML);
			return $sHTML;
		}


		/**
		 * Find all the editable files in a directory
		 *
		 * @param string $search_root_directory
		 * @return array $result
		 */
		private function findEditableFiles($search_root_directory){
			# Global other vars
			global $root_directory, $directories, $files, $extensions_files_found, $tags_files_found;

			$result = false;

			$handle = opendir($search_root_directory);
			while(($name = readdir($handle)) !== false){
				if($name == '.' || $name == '..'){
					continue;
				}

				if(is_dir($search_root_directory . $name) && !in_array(substr($search_root_directory . $name,strlen($root_directory)),explode(',',EXCLUDE_DIRECTORIES))){
					if($this->findEditableFiles($search_root_directory . $name . '/') === true){
						$directories[] = $search_root_directory . $name . '/';
						$result = true;
					}
				}else{
					$extension = (strpos($name,'.') === false ? '' : strtolower(substr($name,strrpos($name,'.')+1)));
					if(in_array($extension,$this->allowed_extensions)){
						$extensions_files_found++;

						$content = file_get_contents($search_root_directory . $name);
						if(ini_get('magic_quotes_runtime')){
							$content = stripslashes($content);
						}
						if(preg_match_all('/(' . str_replace(array(' ','"','%s'),array('\s*','(\'|"){1}','([^"\']+)'),implode('|',$this->editable_region_tags_start)) . ')(.*)(' . str_replace(' ','\s*',implode('|',$this->editable_region_tags_end)) . ')/ismU',$content,$matches)){
							$tags_files_found++;

							$regions = array();
							foreach($matches[3] as $i => $region_name){
								if(trim($region_name) == '' || in_array(trim($region_name),explode(',',IGNORE_EDITABLE_REGION_NAMES))){
									continue;
								}
								$regions[] = $region_name;
							}
							foreach($matches[6] as $i => $region_name){
								if(trim($region_name) == '' || in_array(trim($region_name),explode(',',IGNORE_EDITABLE_REGION_NAMES))){
									continue;
								}
								$regions[] = $region_name;
							}

							$files[$search_root_directory . $name] = $regions;
							$result = true;
						}
					}
				}
			}

			return $result;
		}


		/**
		 * Show an overview of the pages
		 * @param $return_array = false
		 *
		 */
		private function pageOverview($return_array = false){
			global $root_directory, $directories, $files, $extensions_files_found, $tags_files_found;

			# Header
			if(!$return_array){
				echo '
                <div class="row">
				<div class="col-lg-12">

				<h1 class="page-header">' . $this->getLanguage('pages') . '</h1>

				</div>
				<!-- /.col-lg-12 -->
				</div>
				<!-- /.row -->';
			}

			# Vars
			$handle = false;
			$files = array();
			$directories = array();
			$extensions_files_found = 0;
			$tags_files_found = 0;

			# Current directory
			if(isset($_GET['directory']) && $_GET['directory'] != '' && strpos($_GET['directory'],'../') === false && substr($_GET['directory'],-1) == '/' && $_GET['directory'] != '..'){
				$current_directory = $_GET['directory'];
			}else{
				$current_directory = null;
			}

			# Try absolute path first
			if(is_dir(ROOT_DIRECTORY . $current_directory)){
				$handle = opendir(ROOT_DIRECTORY . $current_directory);
				$root_directory = ROOT_DIRECTORY . $current_directory;
			}

			# Try relative path second
			if(!is_resource($handle)){
				$handle = opendir('../' . $current_directory);
				$root_directory = '../' . $current_directory;
			}

			# One of first two options worked
			if(is_resource($handle)){
				$this->findEditableFiles($root_directory);

			# Try FTP last
			}else{
				// Maybe next update?
			}

			# Sort directories and files
			sort($directories);
			ksort($files);

			# Return array
			if($return_array){
				return $files;
			}

			# Directory cannot be read
			if($handle === false){
				echo '<p class="message">' . $this->getLanguage('the_directory_cannot_be_read_please_check_if_the_directory_exists_and_has_the_right_privileges',ROOT_DIRECTORY . $current_directory) . '</p>';
				return;
			}

			# No files found with the right extensions
			if($extensions_files_found == 0){
				echo '<p class="message">' . $this->getLanguage('there_are_no_files_found_with_the_extensions',implode(', ',str_replace('%s','*',$this->allowed_extensions))) . '</p>';
				return;
			}

			# No files found with the right tags
			if($tags_files_found == 0){
				echo '<p class="message">' . $this->getLanguage('there_are_no_files_found_with_the_tags',htmlspecialchars(implode(' ' . $this->getLanguage('or') . ' ',$this->editable_region_tags_start))) . '</p>';
				return;
			}

			# Add new page
			if($this->administrator || USER_ADD_NEW_PAGES){
				echo '<p><a href="./index.php?page=page_add' . ($current_directory != null ? '&directory=' . urlencode($current_directory) : '') . '"><i class="fa fa-plus-circle"></i> ' . $this->getLanguage('add_new_page') . '</a></p>';
			}
			echo '<div class="row">';

			# Show files
			echo '	<table id="files-section" class="table table-hover dataTable no-footer large-only stacktable">
						<tr role="row">
							<th style="width: 20px;"></th>
							<th>' . $this->getLanguage('filename') . '</th>
							<th>' . $this->getLanguage('editable_regions') . '</th>
							<th></th>
						</tr>';

			$odd = 0;
			if($current_directory != null){
				$directory_up = null;
				if(strpos(substr($current_directory,0,-1),'/') !== false){
					$directory_up = substr($current_directory,0,strrpos(substr($current_directory,0,-1),'/')+1);
				}

				echo '	<tr class="' . ($odd++ % 2 ? 'odd ' : '') . 'gray">
							<td><i class="fa fa-undo" title="' . $this->getLanguage('directory_up') . '"></i></td>
							<td colspan="2"><a href="./index.php?page=pages' . ($directory_up != null ? '&amp;directory=' . urlencode($directory_up) : '') . '">' . $this->getLanguage('directory_up') . '</a></td>
							<td></td>
						</tr>';
			}

			foreach($directories as $directory){
				$directory = substr($directory,strlen($root_directory),-1);

				if(strpos($directory,'/') !== false){
					continue;
				}

				echo '	<tr' . ($odd++ % 2 ? ' class="odd"' : '') . '>
							<td><i class="fa fa-folder" title="' . htmlspecialchars($directory) . '"></i></td>
							<td colspan="2"><a href="./index.php?page=pages&amp;directory=' . urlencode($current_directory . $directory . '/') . '">' . htmlspecialchars($directory). '</a></td>
							<td></td>
						</tr>';
			}

			foreach($files as $filename => $regions){
				if(strpos(substr($filename,strlen($root_directory)),'/') !== false){
					continue;
				}

				$filename = substr($filename,strlen($root_directory));

				echo '	<tr' . ($odd++ % 2 ? ' class="odd"' : '') . '>
							<td><a href="' . WEBSITE_URL . $current_directory . $filename . '" target="_blank"><i class="fa fa-file-text-o" title="' . htmlspecialchars($filename) . '"></i></a></td>
							<td>' . htmlspecialchars($filename). '</td>
							<td class="editable_regions">';
				foreach($regions as $i => $region){
				if ($this->user) {
					$prefixes = array("nm_", "js_", "ts_");
					$regionname = str_replace($prefixes, '', $region);
				}else{
                    $regionname = $region;
				}
					echo '<a rel="tooltip" data-html="true" href="./index.php?page=page_edit&amp;filename=' . urlencode($current_directory . $filename) . '&amp;region=' . $region . '" title="' . htmlspecialchars($this->getLanguage('edit_region',$regionname)) . '">' . $regionname . '</a>&nbsp;&nbsp;<i class="fa fa-pencil" title="' . htmlspecialchars($this->getLanguage('edit_page')) . '"></i>&nbsp;&nbsp;';
				}
				echo  '		</td>';

				if(!FTP_ENABLED && !is_writable(ROOT_DIRECTORY . $current_directory . $filename)){
					echo '	<td class="not_writable">' . $this->getLanguage('not_writable') . '</td>';
				}else{
					echo '	<td>
								<!-- <a href="#" title="' . htmlspecialchars($this->getLanguage('edit_page')) . '"><img src="./images/pencil.png" alt="' . htmlspecialchars($this->getLanguage('edit_page')) . '" /></a> -->';
					if($this->administrator || USER_ADD_NEW_PAGES){
						echo '	<a rel="tooltip" href="./index.php?page=page_add' . ($current_directory != '' ? '&amp;directory=' . urlencode($current_directory) : '') . '&amp;page_filename=' . urlencode($filename) . '" title="' . htmlspecialchars($this->getLanguage('copy_page')) . '"><i class="fa fa-files-o" title="' . htmlspecialchars($this->getLanguage('copy_page')) . '"></i></a>';
					}
					if($this->administrator || USER_DELETE_PAGES){
						echo '&nbsp;&nbsp;<a rel="tooltip" href="./index.php?page=page_delete&amp;filename=' . urlencode($current_directory . $filename) . '&hash=' . sha1(ADMINISTRATOR_PASSWORD . $current_directory . $filename) . '" onclick="return confirm(\'' . htmlspecialchars(strip_tags($this->getLanguage('are_you_sure_you_want_to_delete',$filename))) . '\');" title="' . htmlspecialchars($this->getLanguage('delete_page')) . '"><i class="fa fa-trash-o" title="' . htmlspecialchars($this->getLanguage('delete_page')) . '"></i></a>';
					}
					echo '	</td>';
				}
				echo '	</tr>';
			}
			echo '	</table>';
			echo '</div';
		}


		/**
		 * Edit the configuration settings
		 *
		 */
		private function editConfiguration(){
			# Check if FTP functions are available
			$ftp_enabled = (function_exists('ftp_connect') ? '1' : '0');

			# Header
			echo '
            <div class="row">
				<div class="col-lg-12">
					<h1 class="page-header">' . $this->getLanguage('configuration') . '</h1>
            	</div>
			</div>';

			# Message
			if($_POST){
				if(trim($_POST['user_username']) == '' || trim($_POST['user_password']) == '' || trim($_POST['administrator_username']) == '' || trim($_POST['administrator_password']) == '' || ($_POST['ftp_enabled'] && (trim($_POST['ftp_host']) == '' || trim($_POST['ftp_port']) == '' || trim($_POST['ftp_username']) == '' || trim($_POST['ftp_password']) == '' || trim($_POST['ftp_root_directory']) == ''))){
					echo '<p class="error">' . $this->getLanguage('please_fill_in_the_required_fields') . '</p>';
				}else{
					# Check FTP details
					$ftp_error = false;
					if($_POST['ftp_enabled'] == '1'){
						# Make sure the root directory has a trailing slash
						$_POST['ftp_root_directory'] = $_POST['ftp_root_directory'] . (substr($_POST['ftp_root_directory'],-1) != '/' ? '/' : '');

						$ftp_error = !$this->checkFTP($_POST['ftp_host'],$_POST['ftp_port'],$_POST['ftp_passive_mode'],$_POST['ftp_username'],$_POST['ftp_password'],$_POST['ftp_root_directory']);
					}else{
						$_POST['ftp_host'] = '';
						$_POST['ftp_port'] = '';
						$_POST['ftp_passive_mode'] = '';
						$_POST['ftp_username'] = '';
						$_POST['ftp_password'] = '';
						$_POST['ftp_root_directory'] = '';
					}

					# Write new config.php
					if(!$ftp_error){
						$content = file_get_contents('./config.php');
						if(ini_get('magic_quotes_runtime')){
							$content = stripslashes($content);
						}

						if(!isset($_POST['user_font_family'])) {
						     $user_font_family = '0';
						} else {
						     $user_font_family = $_POST['user_font_family'];
						}
						if(!isset($_POST['user_font_size'])) {
						     $user_font_size = '0';
						} else {
						     $user_font_size = $_POST['user_font_size'];
						}
						if(!isset($_POST['user_font_color'])) {
						     $user_font_color = '0';
						} else {
						     $user_font_color = $_POST['user_font_color'];
						}
						if(!isset($_POST['user_edit_title'])) {
						     $user_edit_title = '0';
						} else {
						     $user_edit_title = $_POST['user_edit_title'];
						}
						if(!isset($_POST['user_edit_keywords'])) {
						     $user_edit_keywords = '0';
						} else {
						     $user_edit_keywords = $_POST['user_edit_keywords'];
						}
						if(!isset($_POST['user_edit_description'])) {
						     $user_edit_description = '0';
						} else {
						     $user_edit_description = $_POST['user_edit_description'];
						}
						if(!isset($_POST['user_delete_pages'])) {
						     $user_delete_pages = '0';
						} else {
						     $user_delete_pages = $_POST['user_delete_pages'];
						}
						if(!isset($_POST['user_add_new_pages'])) {
						     $user_add_new_pages = '0';
						} else {
						     $user_add_new_pages = $_POST['user_add_new_pages'];
						}
						if (!isset($_POST['exclude_directories'])) {
							$exclude_directories = '';
						}else{
                            $exclude_directories = implode(',',$_POST['exclude_directories']);
						}

						$replace = array(
										'EDITOR_WIDTH' => $_POST['editor_width'],
										'EDITOR_HEIGHT' => $_POST['editor_height'],
										'CHARSET' => $_POST['charset'],
										'UPLOAD_DIRECTORY' => $_POST['upload_directory'],
										'EXCLUDE_DIRECTORIES' => $exclude_directories,

										'USER_USERNAME' => $_POST['user_username'],
										'USER_PASSWORD' => ($_POST['user_password'] != '******' ? sha1($_POST['user_password']) : USER_PASSWORD),
										'USER_LANGUAGE' => $_POST['user_language'],

										'USER_FONT_FAMILY' => $user_font_family,
										'USER_FONT_SIZE' => $user_font_size,
										'USER_FONT_COLOR' => $user_font_color,
										'USER_EDIT_TITLE' => $user_edit_title,
										'USER_EDIT_KEYWORDS' => $user_edit_keywords,
										'USER_EDIT_DESCRIPTION' => $user_edit_description,
										'USER_DELETE_PAGES' => $user_delete_pages,
										'USER_ADD_NEW_PAGES' => $user_add_new_pages,

										'ADMINISTRATOR_USERNAME' => $_POST['administrator_username'],
										'ADMINISTRATOR_PASSWORD' => ($_POST['administrator_password'] != '******' ? sha1($_POST['administrator_password']) : ADMINISTRATOR_PASSWORD),
										'ADMINISTRATOR_LANGUAGE' => $_POST['administrator_language'],

										'FTP_ENABLED' => $_POST['ftp_enabled'],
										'FTP_HOST' => $_POST['ftp_host'],
										'FTP_PORT' => $_POST['ftp_port'],
										'FTP_PASSIVE_MODE' => $_POST['ftp_passive_mode'],
										'FTP_USERNAME' => $_POST['ftp_username'],
										'FTP_PASSWORD' => $_POST['ftp_password'],
										'FTP_ROOT_DIRECTORY' => $_POST['ftp_root_directory']
									);

						foreach($replace as $key => $value){
							$content = preg_replace('/define\(\'' . $key . '\',\'[^\']*\'\);/ism','define(\'' . $key . '\',\'' . str_replace("'","\'",$value) . '\');',$content);
						}

						# Demo mode
						if(DEMO_MODE){
							echo '<p class="message">' . $this->getLanguage('the_changes_have_not_been_saved_because_of_the_demo_mode') . '</p>';
							return;
						}

						if($_POST['ftp_enabled']){
							$result = $this->filePutContentsFTP('./' . CMS_DIRECTORY . '/config.php', $content, true, $_POST['ftp_host'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password'], $_POST['ftp_passive_mode'], $_POST['ftp_root_directory']);
						}else{
							$result = $this->filePutContentsPHP('./' . CMS_DIRECTORY . '/config.php', $content, ROOT_DIRECTORY);
						}

						if($result){
							echo '<p class="message">' . $this->getLanguage('the_new_configuration_has_been_saved_succesfully') . '</p>';
							return;
						}else{
							echo '<p class="error">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>./' . CMS_DIRECTORY . '/config.php</strong>') . '</p>';
						}
					}
				}
			}

			# Form
			$odd = 0;
			echo '
            <form class="form-horizontal" role="form" name="configuration" action="./index.php?page=configuration" method="post">
            <div class="row">
			<div class="col-lg-12">

			<div class="form-group">
			<h3>' . $this->getLanguage('settings') . '</h3>
				<label for="editor_width" class="col-sm-2 control-label">' . $this->getLanguage('editor_width') . '</label>
				<div class="col-md-2">
					<input type="text" class="form-control" id="editor_width" name="editor_width" tabindex="1" value="' . htmlentities($_POST ? $_POST['editor_width'] : EDITOR_WIDTH) . '">
				</div>
			</div>

            <div class="form-group">
				<label for="editor_width" class="col-sm-2 control-label">' . $this->getLanguage('editor_height') . '</label>
            	<div class="col-md-2">
            		<input type="text" class="form-control" id="editor_height" name="editor_height" tabindex="2" value="' . htmlentities($_POST ? $_POST['editor_height'] : EDITOR_HEIGHT) . '">
            	</div>
			</div>

            <div class="form-group">
				<label for="editor_width" class="col-sm-2 control-label">' . $this->getLanguage('charset') . '</label>
            	<div class="col-md-2">
            		<input type="text" class="form-control" id="charset" name="charset" tabindex="3" value="' . htmlentities($_POST ? $_POST['charset'] : CHARSET) . '">
            	</div>
			</div>

			<div class="form-group">
			    <label class="col-sm-2 control-label">' . $this->getLanguage('upload_directory') . ' *</label>
				<div class="col-md-4">
				<select tabindex="5" class="form-control" name="upload_directory">
			        <option value="">' . $this->getLanguage('root_directory') . '</option>';
					$handle = opendir(ROOT_DIRECTORY);
					while(($filename = readdir($handle)) !== false){
						if($filename == '.' || $filename == '..' || !is_dir(ROOT_DIRECTORY . $filename)){
							continue;
						}
						$directories[] = $filename;
					}
					natsort($directories);

					foreach($directories as $filename){
						echo '<option value="' . $filename . '/"' . ((isset($_POST['upload_directory']) && $_POST['upload_directory'] == $filename . '/') || (!isset($_POST['upload_directory']) && UPLOAD_DIRECTORY == $filename . '/') ? ' selected="selected"' : '') . '>' . $filename . '</option>';
					}
				echo '</select>
				</div>
			</div>

            <div class="form-group">
            	<label class="col-sm-2 control-label">' . $this->getLanguage('exclude_directories') . '</label>
				<div class="col-md-2 label-checkbox switch">';
					foreach($directories as $filename){
						echo '<label class="checkbox switch"><input class="js-switch" tabindex="7" type="checkbox" name="exclude_directories[]" value="' . $filename . '"' . ((isset($_POST['exclude_directories']) && in_array($filename,$_POST['exclude_directories'])) || (!isset($_POST['exclude_directories']) && in_array($filename,explode(',',EXCLUDE_DIRECTORIES))) ? ' checked="checked"' : '') . '> ' . $filename . '</label>';
					}
			echo '</label>
				</div>
        	</div>

			<div class="form-group">
			<h3>' . $this->getLanguage('user') . '</h3>
				<label for="user_username" class="col-sm-2 control-label">' . $this->getLanguage('username') . '</label>
				<div class="col-md-3">
					<input type="text" class="form-control" id="user_username" name="user_username" tabindex="8" value="' . htmlentities($_POST ? $_POST['user_username'] : USER_USERNAME) . '">
				</div>
			</div>

			<div class="form-group">
				<label for="user_username" class="col-sm-2 control-label">' . $this->getLanguage('password') . '</label>
				<div class="col-md-3">
					<input type="password" class="form-control" id="user_password" name="user_password" tabindex="9" value="' . htmlentities($_POST ? $_POST['user_password'] : '******') . '">
				</div>
			</div>

			<div class="form-group">
			    <label class="col-sm-2 control-label">' . $this->getLanguage('language') . ' *</label>
				<div class="col-md-3">
				<select class="form-control" name="user_language" tabindex="10">';
					$languages = array();
					$handle = opendir('./languages/');
					while(($name = readdir($handle)) !== false){
						if(substr($name,-4) != '.ini'){
							continue;
						}
						$languages[substr($name,0,-4)] = (array_key_exists(substr($name,0,-4),$this->language_texts) ? $this->getLanguage(substr($name,0,-4)) : substr($name,0,-4));
					}
					foreach($languages as $name => $value){
						echo '<option value="' . $name . '"' . ((isset($_POST['user_language']) && $_POST['user_language'] == $name) || (!isset($_POST['user_language']) && USER_LANGUAGE == $name) ? ' selected="selected"' : '') . '>' . $value . '</option>';
					}
				echo '</select>
				</div>
			</div>

            <div class="form-group">
			<label class="col-sm-2 control-label">' . $this->getLanguage('edit_title') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					<input type="checkbox" name="user_edit_title" class="js-switch" value="1"' . (($_POST && $_POST['user_edit_title'] == '1') || (!$_POST && USER_EDIT_TITLE == '1') ? ' checked="checked"' : '') . ' />
                </label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('edit_keywords') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					<input type="checkbox" name="user_edit_keywords" class="js-switch" value="1"' . (($_POST && $_POST['user_edit_keywords'] == '1') || (!$_POST && USER_EDIT_KEYWORDS == '1') ? ' checked="checked"' : '') . '>
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('edit_description') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					<input type="checkbox" name="user_edit_description" class="js-switch" value="1"' . (($_POST && $_POST['user_edit_description'] == '1') || (!$_POST && USER_EDIT_DESCRIPTION == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('font_family') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					<input type="checkbox" name="user_font_family" class="js-switch" value="1"' . (($_POST && $_POST['user_font_family'] == '1') || (!$_POST && USER_FONT_FAMILY == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('font_size') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					 <input type="checkbox" name="user_font_size" class="js-switch" value="1"' . (($_POST && $_POST['user_font_size'] == '1') || (!$_POST && USER_FONT_SIZE == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('font_color') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					 <input type="checkbox" name="user_font_color" class="js-switch" value="1"' . (($_POST && $_POST['user_font_color'] == '1') || (!$_POST && USER_FONT_COLOR == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('delete_pages') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					 <input type="checkbox" name="user_delete_pages" class="js-switch" value="1"' . (($_POST && $_POST['user_delete_pages'] == '1') || (!$_POST && USER_DELETE_PAGES == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
				<div class="clearfix"></div>

			<label class="col-sm-2 control-label">' . $this->getLanguage('add_new_pages') . ' *</label>
				<div class="col-md-10">
				<label class="radio-inline switch">
					 <input type="checkbox" name="user_add_new_pages" class="js-switch" value="1"' . (($_POST && $_POST['user_add_new_pages'] == '1') || (!$_POST && USER_ADD_NEW_PAGES == '1') ? ' checked="checked"' : '') . ' />
				</label>
				</div>
			</div>

			<div class="form-group">
			<h3>' . $this->getLanguage('administrator') . '</h3>
				<label for="user_username" class="col-sm-2 control-label">' . $this->getLanguage('username') . '</label>
				<div class="col-md-3">
					<input type="text" class="form-control" id="administrator_username" name="administrator_username" tabindex="27" value="' . htmlentities($_POST ? $_POST['administrator_username'] : ADMINISTRATOR_USERNAME) . '">
				</div>
			</div>

			<div class="form-group">
				<label for="user_username" class="col-sm-2 control-label">' . $this->getLanguage('password') . '</label>
				<div class="col-md-3">
					<input type="password" class="form-control" id="administrator_password" name="administrator_password" tabindex="28" value="' . htmlentities($_POST ? $_POST['administrator_password'] : '******') . '">
				</div>
			</div>

			<div class="form-group">
			    <label class="col-sm-2 control-label">' . $this->getLanguage('language') . ' *</label>
				<div class="col-md-3">
				<select class="form-control" name="administrator_language" tabindex="29">';
					foreach($languages as $name => $value){
						echo '<option value="' . $name . '"' . ((isset($_POST['administrator_language']) && $_POST['administrator_language'] == $name) || (!isset($_POST['administrator_language']) && ADMINISTRATOR_LANGUAGE == $name) ? ' selected="selected"' : '') . '>' . $value . '</option>';
					}
				echo '</select>
				</div>
			</div>

			<div class="form-group">
			<h3>' . $this->getLanguage('ftp') . '</h3>
			<label class="col-sm-2"></label>
				<div class="col-md-10">
				<label class="radio-inline">
	            	<input type="radio" tabindex="30" name="ftp_enabled" id="ftp_enabled_yes" value="1"' . (($_POST && $_POST['ftp_enabled'] == '1') || (!$_POST && FTP_ENABLED == '1') ? ' checked="checked"' : '') . ' /> ' . $this->getLanguage('yes') . '
				</label>
				<label class="radio-inline">
					<input type="radio" tabindex="31" name="ftp_enabled" value="0"' . (($_POST && $_POST['ftp_enabled'] == '0') || (!$_POST && FTP_ENABLED == '0') ? ' checked="checked"' : '') . ' /> ' . $this->getLanguage('no') . '
				</label>
				</div>
			</div>

			<div class="form-group">
			<label for="ftp_host" class="col-sm-2 control-label">' . $this->getLanguage('host') . '</label>
				<div class="col-md-8">
					<input type="text" tabindex="32" name="ftp_host" class="form-control ftp_field" value="' . htmlentities($_POST ? $_POST['ftp_host'] : FTP_HOST) . '" />
				</div>
			</div>

			<div class="form-group">
			<label for="ftp_port" class="col-sm-2 control-label">' . $this->getLanguage('port') . '</label>
				<div class="col-md-2">
					<input type="text" tabindex="33" class="form-control ftp_field" name="ftp_port" value="' . htmlspecialchars($_POST ? $_POST['ftp_port'] : FTP_PORT) . '" ' . ((isset($_POST['ftp_enabled']) && $_POST['ftp_enabled'] == '0') || (!isset($_POST['ftp_enabled']) && $ftp_enabled == '0') ? ' disabled="disabled"' : '') . ' />
				</div>
			</div>

            <div class="form-group">
			<label class="col-sm-2 control-label">' . $this->getLanguage('passive_mode') . '</label>
				<div class="col-md-10">
					<label class="radio-inline">
						<input type="radio" tabindex="34" name="ftp_passive_mode" class="ftp_field" value="1"' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '1') || (!isset($_POST['ftp_passive_mode']) && FTP_PASSIVE_MODE == '1') ? ' checked="checked"' : '') . ' /> ' . $this->getLanguage('yes') . '
					</label>
					<label class="radio-inline">
						<input type="radio" tabindex="35" name="ftp_passive_mode" class="ftp_field" value="0"' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '0') || (!isset($_POST['ftp_passive_mode']) && FTP_PASSIVE_MODE == '0') ? ' checked="checked"' : '') . ' /> ' . $this->getLanguage('no') . '
					</label>
					<label class="radio-inline">
						<input type="radio" tabindex="36" name="ftp_passive_mode" class="ftp_field" value=""' . ((isset($_POST['ftp_passive_mode']) && $_POST['ftp_passive_mode'] == '') || (!isset($_POST['ftp_passive_mode']) && FTP_PASSIVE_MODE == '') ? ' checked="checked"' : '') . ' /> ' . $this->getLanguage('default') . '
					</label>
				</div>
			</div>

			<div class="form-group">
			<label for="ftp_username" class="col-sm-2 control-label">' . $this->getLanguage('user') . '</label>
				<div class="col-md-3">
					<input type="text" tabindex="37" name="ftp_username" class="form-control ftp_field" value="' . htmlentities($_POST ? $_POST['ftp_username'] : FTP_USERNAME) . '" />
				</div>
			</div>

			<div class="form-group">
			<label for="ftp_password" class="col-sm-2 control-label">' . $this->getLanguage('password') . '</label>
				<div class="col-md-3">
					<input type="password" tabindex="38" name="ftp_password" class="form-control ftp_field" value="' . htmlentities($_POST ? $_POST['ftp_password'] : FTP_PASSWORD) . '" />
				</div>
			</div>

			<div class="form-group">
			<label for="ftp_root_directory" class="col-sm-2 control-label">' . $this->getLanguage('root_directory') . '</label>
				<div class="col-md-8">
					<input type="text" tabindex="39" name="ftp_root_directory" class="form-control ftp_field" value="' . htmlentities($_POST ? $_POST['ftp_root_directory'] : FTP_ROOT_DIRECTORY) . '" />
				</div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('server_ip_address') . '</strong>
				</div>
				<div class="col-md-8">
					' . SERVER_ADDR . '
				</div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('website_url') . '</strong>
				</div>
				<div class="col-md-8">
					' . WEBSITE_URL . '
				</div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('ignore_editable_region_names') . '</strong>
				</div>
				<div class="col-md-8">
					' . IGNORE_EDITABLE_REGION_NAMES . '
				</div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('allowed_extensions') . '</strong>
				</div>
				<div class="col-md-8">
					' . ALLOWED_EXTENSIONS . '
				</div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('editable_region_tags') . '</strong>
				</div>
			<div class="col-md-8">';
				foreach($this->editable_region_tags_start as $i => $start){
					echo htmlspecialchars(sprintf($start,'*') . sprintf($this->editable_region_tags_end[$i],'*')) . '<br />';
				}
			echo' </div>
			</div>

			<div class="col-md-12">
				<div class="col-md-3">
					<strong>' . $this->getLanguage('automatic_editor_css') . '</strong>
				</div>
				<div class="col-md-8">
					' . (!file_exists('./editor.css') || filesize('./editor.css') == 0 ? '<i class="fa fa-check-square-o" title="' . $this->getLanguage('yes') . '"></i> ' . str_replace('<strong>' . CMS_DIRECTORY . '</strong>',CMS_DIRECTORY,$this->getLanguage('if_you_want_to_disable_or_overwride_this_automatic_css_you_can_put_your_css_in_the_file_editor_css_flatcms_will_automatically_start_using_this_file_when_filled',CMS_DIRECTORY)) : $this->getLanguage('no_because_is_not_empty',CMS_DIRECTORY . '/editor.css')) . '
				</div>
			</div>

			<div class="clearfix divider"></div>

			<div class="col-md-12">
				<div class="col-md-2 text-right">
				</div>
				<div class="col-md-8">
                	<input type="button" tabindex="41" name="cancel" class="btn btn-primary" value="' . $this->getLanguage('cancel') . '" onclick="document.location.href = \'./\';">
					<input type="submit" tabindex="40" name="submit" class="btn btn-success" value="' . $this->getLanguage('save') . '">
					<br />* ' . $this->getLanguage('these_fields_are_mandatory') . '
				</div>
			</div>
			</form>
			<div class="clearfix divider"></div>
			</div>
		</div>';
		}


		/**
		 * Delete a file. With FTP if possible, otherwise through PHP
		 *
		 * @param string $filename
		 * @return bool
		 */
		private function fileDelete($filename){
			if(!file_exists(ROOT_DIRECTORY . $filename)){
				return false;
			}

			if(FTP_ENABLED){
				# Open FTP connection
				$handle = ftp_connect(FTP_HOST, FTP_PORT, 5);

				# Error connecting
				if(!is_resource($handle)){
					echo '<p class="error">' . $this->getLanguage('opening_a_connection_with_on_port_failed',FTP_HOST,FTP_PORT) .  '</p>';
					return false;
				}

				# Login on FTP server
				$login = ftp_login($handle, FTP_USERNAME, FTP_PASSWORD);

				# Error logging in
				if($login === false){
					echo '<p class="error">' . $this->getLanguage('login_with_username_failed_please_check_the_ftp_logins',FTP_USERNAME) .  '</p>';
					return false;
				}

				# Passive mode
				if(FTP_PASSIVE_MODE != ''){
					ftp_pasv($handle, (bool) FTP_PASSIVE_MODE);
				}

				# Write tmp file to FTP
				ftp_delete($handle, FTP_ROOT_DIRECTORY . $filename);

				# Close handle
				ftp_close($handle);
			}else{
				unlink(ROOT_DIRECTORY . $filename);
			}

			return (bool) !file_exists(ROOT_DIRECTORY . $filename);
		}


		/**
		 * Add a new page
		 *
		 */
		private function pageAdd(){
			# Header
			echo '
            <div class="row">
				<div class="col-lg-12">
					<h1 class="page-header">' . $this->getLanguage('add_new_page') . '</h1>
            	</div>
			</div>';

			# List of pages
			$files = $this->pageOverview(true);
			$files_sorted = array();

			foreach($files as $filename => $regions){
				$filename = substr($filename,strlen(ROOT_DIRECTORY));

				if(isset($_GET['directory']) && $_GET['directory'] != ''){
					$filename = str_replace($_GET['directory'],'',$filename);
				}

				if(strpos($filename,'/') !== false){
					continue;
				}

				$files_sorted[$filename] = $regions[0];
			}
			ksort($files_sorted);

			# Save
			if($_POST){
				# Check fields
				if(trim($_POST['page_filename']) == '' || trim($_POST['filename']) == ''){
					echo '<p class="alert alert-danger">' . $this->getLanguage('please_fill_in_the_required_fields') . '</p>';
				}elseif(strpos($_POST['filename'],'.') === false || !in_array(strtolower(substr($_POST['filename'],strpos($_POST['filename'],'.')+1)),$this->allowed_extensions)){
					echo '<p class="alert alert-danger">' . $this->getLanguage('please_use_one_of_the_following_extensions',implode(', ',$this->allowed_extensions)) . '</p>';
				}elseif(!preg_match('/^[a-z0-9_\-\.]+$/i',$_POST['filename'])){
					echo '<p class="alert alert-danger">' . $this->getLanguage('please_check_the_characters_used_in_the_filename_only_these_characters_are_allowed','a-z0-9.-_') . '</p>';
				}else{
					# Demo mode
					if(DEMO_MODE){
						echo '<p class="alert alert-danger">' . $this->getLanguage('the_changes_have_not_been_saved_because_of_the_demo_mode') . '</p>';
						return;
					}

					# Create page
					$content = file_get_contents(ROOT_DIRECTORY . (isset($_GET['directory']) ? $_GET['directory'] : '') . $_POST['page_filename']);
					if(ini_get('magic_quotes_runtime')){
						$content = stripslashes($content);
					}
					$result = $this->filePutContents((isset($_GET['directory']) ? $_GET['directory'] : '') . strtolower($_POST['filename']), $content);

					# Redirect
					if($result !== false){
						header('Location: ./index.php?page=page_edit&filename=' . urlencode((isset($_GET['directory']) ? $_GET['directory'] : '') . $_POST['filename']) . '&region=' . $files_sorted[$_POST['page_filename']]);
						exit;
					}else{
						echo '<p class="alert alert-danger">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>' . $_POST['filename'] . '</strong>') . '</p>';
					}
				}
			}

			# Form
			echo '
			<form class="form-horizontal" role="form" name="add_new_page" action="./index.php?page=page_add' . (isset($_GET['directory']) && $_GET['directory'] != '' && $_GET['directory'] != './' ? '&directory=' . urlencode($_GET['directory']) : '') . '" method="post">
			<div class="col-lg-6">

			<fieldset>
			<div class="form-group">
			<label class="col-sm-6 control-label">' . $this->getLanguage('based_on_the_page') . ' *</label>

				<div class="col-md-6">
				<select class="form-control" name="page_filename" tabindex="1">
				       <option value=""></option>';
						foreach($files_sorted as $filename => $region){
							echo '<option value="' . $filename . '"' . (($_POST && $_POST['page_filename'] == $filename) || (!$_POST && isset($_GET['page_filename']) && $_GET['page_filename'] == $filename) ? ' selected="selected"' : '') . '>' . $filename . '</option>';
						}

						echo '</select>
				</div>
			</div>

			<div class="form-group">
			<label for="filename" class="col-sm-6 control-label">' . $this->getLanguage('filename') . ' *</label>
				<div class="col-md-6">
					<input type="text" tabindex="2" name="filename" value="' . htmlspecialchars($_POST ? $_POST['filename'] : '') . '" class="form-control">
				</div>
			</div>

			<div class="form-group">
			<label class="col-sm-6 control-label"></label>
				<div class="col-md-6">
					<button class="btn btn-primary" tabindex="4" type="button" name="cancel" onclick="document.location.href = \'index.php?page=pages' . (isset($_GET['directory']) && $_GET['directory'] != '' && $_GET['directory'] != './' ? '&directory=' . urlencode($_GET['directory']) : '') . '\';">' . $this->getLanguage('cancel') . '</button>
					<button class="btn btn-success" tabindex="3" type="submit" name="submit">' . $this->getLanguage('add') . '</button>
				</div>
			</div>
			</div>
			<div class="clearfix"></div>
			</form>';
		}


		/**
		 * Edit the contents of a page (region)
		 *
		 * @param string $filename
		 * @param string $region
		 */
		private function pageEdit($filename, $region){
			# Header
			echo '
            <div class="row">
				<div class="col-lg-12">
					<h1 class="page-header">' . $this->getLanguage('edit_page') . ' <span class="page-header-name">' . $filename . '</span></h1>
            	</div>
			</div>';

			# Directory
			if(strpos($filename,'/') !== false){
				$directory = substr($filename,0,strrpos($filename,'/')+1);
			}else{
				$directory = '';
			}

			# Get the content
			$content = file_get_contents(ROOT_DIRECTORY . $filename);
			if(ini_get('magic_quotes_runtime')){
				$content = stripslashes($content);
			}

			# Save
			if($_POST){
				# Title
				if(isset($_POST['title'])){
					$content = preg_replace('#(<title>)([^<]*)(</title>)#ism','$1' . $_POST['title'] . '$3',$content);
				}

				# Keywords
				if(isset($_POST['keywords'])){
					if(preg_match_all('#<meta([^<]+)>#ism',$content,$matches)){
						foreach($matches[1] as $i => $match){
							if(preg_match('#name\s*=\s*("|\'){1}keywords("|\'){1}#ism',$match,$matches_n) && preg_match('#(content\s*=\s*("|\'))([^"\']*)("|\')#ism',$match,$matches_v)){
								$meta = str_replace($matches_v[0],$matches_v[1] . $_POST['keywords'] = str_replace(array('\'', '"'), '', $_POST['keywords']) . $matches_v[4],$matches[0][$i]);
								$content = str_replace($matches[0][$i],$meta,$content);
							}
						}
					}
				}

				# Description
				if(isset($_POST['description'])){
					if(preg_match_all('#<meta([^<]+)>#ism',$content,$matches)){
						foreach($matches[1] as $i => $match){
							if(preg_match('#name\s*=\s*("|\'){1}description("|\'){1}#ism',$match,$matches_n) && preg_match('#(content\s*=\s*("|\'))([^"\']*)("|\')#ism',$match,$matches_v)){
								$meta = str_replace($matches_v[0],$matches_v[1] . $_POST['description'] = str_replace(array('\'', '"'), '', $_POST['description']) . $matches_v[4],$matches[0][$i]);
								$content = str_replace($matches[0][$i],$meta,$content);
							}
						}
					}
				}

				# HTML
				$content = str_replace('!!FLATCMS-DOLLAR!!','$',preg_replace('/(' . str_replace(array(' ','"','%s'),array('\s*','(\'|"){1}','(' . $region . ')'),implode('|',$this->editable_region_tags_start)) . ')(.*)(' . str_replace(' ','\s*',implode('|',$this->editable_region_tags_end)) . ')/ismU','$1' . str_replace('$','!!FLATCMS-DOLLAR!!',$_POST['elm1']) . '$9',$content));

				# Demo mode
				if(DEMO_MODE){
					echo '<p class="message">' . $this->getLanguage('the_changes_have_not_been_saved_because_of_the_demo_mode') . '</p>';
					return;
				}

				# Save
				$result = $this->filePutContents($filename, $content);

				# Redirect
				if($result !== false){
					header('Location: ./index.php?page=pages' . ($directory != '' && $directory != './' ? '&directory=' . urlencode($directory) : ''));
					exit;
				}else{
					echo '<p class="error">' . $this->getLanguage('an_error_occured_while_writing_to_the_file','<strong>' . $filename . '</strong>') . '</p>';
				}
			}

			# Form
			echo '
			<form class="form-horizontal" role="form" name="edit_page" action="./index.php?page=page_edit&filename=' . urlencode($filename) . '&region=' . urlencode($region) . '" method="post">';

			# Properties
			if($this->administrator || USER_EDIT_TITLE || USER_EDIT_KEYWORDS || USER_EDIT_DESCRIPTION){
				$properties = $this->getPageInformation(ROOT_DIRECTORY . $filename);
                $metapos = stripos($region, 'nm_');
				if($metapos !== false){
				echo '
                    <div class="row">
					<div class="col-lg-12">';
				}else{
				echo '
				<div class="col-lg-12">

				' . (($this->administrator || USER_EDIT_TITLE) && $properties['title'] !== null ? '
					<div class="form-group">
	                    <div class="col-md-2">
							<label for="title" class="col-sm-1 control-label">' . $this->getLanguage('title') . '</label>
						</div>
						<div class="col-md-4">
							<input tabindex="1" type="text" id="title" name="title" value="' . htmlspecialchars($properties['title']) . '" class="form-control input_text">
						</div>
				</div>
				' : '') . '

				' . (($this->administrator || USER_EDIT_DESCRIPTION) && $properties['description'] !== null ? '
				<div class="form-group">
					<div class="col-md-2">
						<label for="description" class="col-sm-1 control-label">' . $this->getLanguage('description') . '</label>
                    </div>
					<div class="col-md-6">
						<input tabindex="3" type="text" id="description" name="description" value="' . htmlspecialchars($properties['description']) . '" class="form-control input_text">
					</div>
				</div>
				' : '') . '

				' . (($this->administrator || USER_EDIT_KEYWORDS) && $properties['keywords'] !== null ? '
				<div class="form-group">
                	<div class="col-md-2">
						<label for="keywords" class="col-sm-1 control-label">' . $this->getLanguage('keywords') . '</label>
					</div>
					<div class="col-md-6">
						<input tabindex="2" type="text" id="keywords" name="keywords" value="' . htmlspecialchars($properties['keywords']) . '" class="form-control input_text">
					</div>
				</div>
				' : '') . '

				</div>
				<div class="row">
				<div class="col-lg-12">';
				}
			}

			# HTML
			if(preg_match('/(' . str_replace(array(' ','"','%s'),array('\s*','(\'|"){1}','(' . $region . ')'),implode('|',$this->editable_region_tags_start)) . ')(.*)(' . str_replace(' ','\s*',implode('|',$this->editable_region_tags_end)) . ')/ismU',$content,$matches)){
				$html = $matches[8];
			}else{
				return;
			}

            // plain js editor with js buttons
            $jsbar = stripos($region, 'js_');
            // plain editor
            $txbar = stripos($region, 'tx_');
			if ($jsbar !== false) {
				echo '<script>edToolbar(\'elm1\');</script><textarea style="width:' . EDITOR_WIDTH . ';height:' . EDITOR_HEIGHT . '" class="js-textarea" tabindex="4" name="elm1" id="elm1" rows="21" cols="60">' . $html . '</textarea>';

			}elseif ($txbar !== false) {
				echo '<textarea style="width:' . EDITOR_WIDTH . ';height:' . EDITOR_HEIGHT . '" class="js-textarea" tabindex="4" name="elm1" id="elm1" rows="21" cols="60">' . $html . '</textarea>';
			}else{

			# Editor
			$toolbar = stripos($region, 'ts_');
			// Toolbar buttons full
			$toolbarfull = 'toolbar1: "searchreplace undo redo | paste | bullist numlist outdent indent | ' . ($this->administrator || $this->user && USER_FONT_COLOR == '1' ? 'forecolor backcolor |' : '') . ' blockquote charmap hr table | fullscreen code | restoredraft",
			toolbar2: "visualblocks removeformat formatselect |' . ($this->administrator || $this->user && USER_FONT_FAMILY == '1' ? ' fontselect' : '') . ' ' . ($this->administrator || $this->user && USER_FONT_SIZE == '1' ? ' fontsizeselect' : '') . ' | bold italic underline strikethrough superscript | alignleft aligncenter alignright | image link unlink anchor",';
            // Toolbar buttons small
			$toolbarsmall = 'toolbar1: "undo redo | bold italic | ' . ($this->administrator || $this->user && USER_FONT_COLOR == '1' ? 'forecolor backcolor |' : '') . ' | code restoredraft",';
			echo '<textarea tabindex="4" name="elm1" id="elm1" rows="10" cols="60">' . $this->encodeHTML($html) . '</textarea>
			<script type="text/javascript">
				tinymce.init({
					// Use this textarea
					selector: "textarea#elm1",

					// Layout
					theme: "modern",
					//skin: "light",
					width: "' . EDITOR_WIDTH . '",
					height: "' . EDITOR_HEIGHT . '",

					// Language
					language : "' . (!file_exists('./tinymce/langs/' . substr($this->language,0,2) . '.js') ? 'en' :  substr($this->language,0,2)) . '",

					// Menu bar
					menubar: false,

					// Save
					autosave_restore_when_empty: false,
					//save_enablewhendirty: true,
					paste_remove_styles: true,

					// CSS
					content_css : "' . (!file_exists('./editor.css') || filesize('./editor.css') == 0 ? WEBSITE_URL . CMS_DIRECTORY . '/index.php?page=editor_css&filename=' . urlencode($filename) . '&region=' . urlencode($region) : WEBSITE_URL . CMS_DIRECTORY . '/editor.css') . '",

					// Image advanced tab
					image_advtab: false,

					// Make URLs absolute
					convert_urls: true,
					relative_urls : true,
					//remove_script_host : true,
					document_base_url : "' . WEBSITE_URL . $directory . '",

					// No old font-tags
					convert_fonts_to_spans : true,

					// Links
                    link_list: "' . WEBSITE_URL . CMS_DIRECTORY . '/index.php?page=internal_link&page_filename=' . urlencode($filename) . '",

					// Formats
					block_formats: "Paragraph=p;Header 1=h1;Header 2=h2;Header 3=h3",

					// Filemanager
					external_filemanager_path: "' . WEBSITE_URL . CMS_DIRECTORY . '/index.php?page=file_manager&page_filename=' . urlencode($filename) . '",
					filemanager_title: "' . $this->getLanguage('file_manager') . '",
					external_plugins: {	filemanager : "' . WEBSITE_URL . CMS_DIRECTORY . '/js/plugin.min.js" },

					// Load plugins
					plugins: [
						"advlist autosave autolink lists link image lists charmap hr anchor",
						"fullscreen code nonbreaking save table contextmenu visualblocks",
						"textcolor paste table importcss searchreplace",
					],

                    ' . ($toolbar !== false ? $toolbarsmall : $toolbarfull) . '

				});
		    </script>';
			}
			echo '
			</div>
			</div>
			<div class="row">
				<div class="col-lg-12">
					<button tabindex="6" class="btn btn-primary btn-editor" type="button" name="cancel" onclick="document.location.href = \'index.php?page=pages' . ($directory != '' && $directory != './' ? '&directory=' . urlencode($directory) : '') . '\';">' . $this->getLanguage('cancel') . '</button>
					<button tabindex="5" class="btn btn-success btn-editor" type="submit" name="submit">' . $this->getLanguage('save') . '</button>
				</div>
			</div>
			</form>';
		}


		/**
		 * Delete a page
		 *
		 * @param string $filename
		 */
		private function pageDelete($filename){
			# Privileges
			if(!$this->administrator && !USER_DELETE_PAGES){
				header('Location: ./index.php?page=pages');
				exit;
			}

			# Header
			echo '<h1>' . $this->getLanguage('delete_page') . '</h1>';

			# Demo mode
			if(DEMO_MODE){
				echo '<p class="message">' . $this->getLanguage('the_changes_have_not_been_saved_because_of_the_demo_mode') .  '</p>';
				return false;
			}

			# Delete the file
			if(!$this->fileDelete($filename)){
				echo '<p class="error">' . $this->getLanguage('an_error_occured_while_deleting_the_file',$filename) .  '</p>';
				return false;
			}

			# Redirect
			header('Location: ./index.php?page=pages' . (strpos($filename,'/') !== false ? '&directory=' . urlencode(dirname($filename) . '/') : ''));
			exit;
		}


		/**
		 * Get the CSS for the Editor
		 *
		 * @param string $filename
		 * @param string $region
		 * @return string $css
		 */
		private function getEditorCSS($filename, $region){
			$general_objects = array('input','select','textarea','div','table','tr','th','td','a','span','p','ul','ol','li','h1','h2','h3','h4','h5','h6');
			$allowed_properties = array('text-','font','color','background');

			# Does the file exist?
			if(!file_exists(ROOT_DIRECTORY . $filename)){
				return false;
			}

			# Directory
			if(dirname($filename) == ''){
				$directory = '';
			}else{
				$directory = dirname($filename) . '/';
			}

			# Get the HTML content
			$html = file_get_contents(ROOT_DIRECTORY . $filename);
			if(ini_get('magic_quotes_runtime')){
				$html = stripslashes($html);
			}

			# Does the file have a content-tag?
			if(!preg_match('/(' . str_replace(array(' ','"','%s'),array('\s*','(\'|"){1}','(' . $region . ')'),implode('|',$this->editable_region_tags_start)) . ')(.*)(' . str_replace(' ','\s*',implode('|',$this->editable_region_tags_end)) . ')/ismU',$html,$matches)){
				return false;
			}
			$content_tag = $matches[1];

			# Get the CSS content
			$original_css = '';
			preg_match_all('#<(link){1}([^>]+)(src|href){1}=("|\'){1}([^"\']+)("|\'){1}([^>]*)>#i',$html,$matches);
			foreach($matches[5] as $index => $path){
				$old_tag = $matches[0][$index];

				# Ignore print stylesheets
				if(preg_match('/media\s*=\s*.?print.?/ism',$old_tag)){
					continue;
				}

				if(substr($path,0,7) != 'http://'){
					$css_content = file_get_contents(ROOT_DIRECTORY . $directory . $path);
					if(ini_get('magic_quotes_runtime')){
						$css_content = stripslashes($css_content);
					}
					$original_css .= $css_content . "\n\n";
				}else{
					$original_css .= $this->urlGetContents($path) . "\n\n";
				}
			}
			preg_match_all('#(<style[^>]*>)(.+)(</style>)#ismU',$html,$matches);
			if(count($matches[2]) > 0){
				foreach($matches[2] as $match){
					$match = str_replace(array('<!--','-->'),'',$match);
					$original_css .= $match . "\n\n";
				}
			}

			# Remove comment and charset definition
			$original_css = preg_replace('#/\*[^/]*\*/#ism','',$original_css);
			$original_css = preg_replace('#@charset[^;]+;#ism','',$original_css);

			# Strip comment in CSS
			$original_css = preg_replace('/(\/\*[\s\S]*?\*\/|[\r]|[\n]|[\r\n])/', '', $original_css);

			# Get all the individual parts
			preg_match_all('/([^{]+){([^}]+)}/ism',$original_css,$matches);

			# Create a DOM object
			require_once('./classes/simplehtmldom.php');
			$dom = new simple_html_dom();
			$dom->load($html);

			# In which element is the content-tag?
			$elements = $dom->find('div, td');
			$content_element = null;
			$content_element_depth = 0;
			foreach($elements as $element){
				if(stripos($element->innertext,$content_tag) !== false){
					$depth = 0;
					$tmp_element = $element;
					while($tmp_element->parent() != null){
						$tmp_element = $tmp_element->parent();
						$depth++;
					}

					if($depth > $content_element_depth){
						$content_element = $element;
						$content_element_depth = $depth;
					}
				}
			}

			# Nothing found
			if($content_element == null){
				return false;
			}

			# Start the new CSS
			$editor_css_parts = array();
			$editor_css_parts['body'] = '';

			# Settings object
			$object_tag = $content_element->tag;
			$object_id = $content_element->id;
			$object_class = $content_element->class;

			# Loop from oldest-parent to eventually the object
			$object = $content_element;
			$objects = array($object);
			while($object->parent() != null){
				$objects[] = $object->parent();
				$object = $object->parent();
			}

			krsort($objects);
			$done = array();
			foreach($objects as $object){
				# Loop trough all the parts
				foreach($matches[1] as $index => $tagline){
					$tags = explode(',',$tagline);
					$part_css = trim($matches[2][$index]);
					if(substr($part_css,-1) != ';'){
						$part_css .= ';';
					}

					# Loop trough all the tags
					foreach($tags as $tag){
						# Trim tag
						$tag = trim($tag);

						# Ignore hovers etc
						if(strpos($tag,':') !== false){
							continue;
						}

						# No parent/children combinations
						if(strpos($tag,' ') !== false){
							continue;
						}

						$match_object = (bool) (preg_match('/^(' . $object->tag . ')?(#' . $object->id . ')?(\.' . $object->class . ')?$/i',$tag,$object_matches) && count((isset($object_matches[0]) ? $object_matches[0] : '')) + count((isset($object_matches[1]) ? $object_matches[1] : '')) + count((isset($object_matches[2]) ? $object_matches[2] : '')) > 0);
						if($match_object && !in_array($index,$done)){
							$done[] = $index;
							$editor_css_parts['body'] .= $part_css;
						}
					}
				}
			}

			# Loop trough all the parts
			foreach($matches[1] as $index => $tagline){
				$tags = explode(',',$tagline);
				$part_css = trim($matches[2][$index]);
				if(substr($part_css,-1) != ';'){
					$part_css .= ';';
				}

				# Loop trough all the tags
				foreach($tags as $tag){
					# Trim tag
					$tag = trim($tag);

					# Ignore hovers etc
					if(strpos($tag,':') !== false){
						continue;
					}

					# Is it about parent/children combinations?
					if(strpos($tag,' ') !== false){
						$subtags = explode(' ',$tag);

						# Last subitem has to fit to content-obj or be a general object
						$match_object = (bool) (preg_match('/^(' . $object_tag . ')?(#' . $object_id . ')?(\.' . $object_class . ')?$/i',$subtags[count($subtags)-1],$object_matches) && count((isset($object_matches[0]) ? $object_matches[0] : '')) + count((isset($object_matches[1]) ? $object_matches[1] : '')) + count((isset($object_matches[2]) ? $object_matches[2] : '')) > 0);
						$last_tag = $subtags[count($subtags)-1];
						$last_tag = preg_replace('/(\.[a-z0-9_\-]+)/i','',$last_tag);
						$is_general_object = (bool) in_array($last_tag,$general_objects);
						if($is_general_object){
							$general_object = $subtags[count($subtags)-1];
						}else{
							$general_object = null;
						}

						if(!$match_object && !$is_general_object){
							continue;
						}

						# Reverse and pop the array to start looking in the second last one
						array_pop($subtags);
						rsort($subtags);

						# Loop trough all the subtags
						$failed = false;
						foreach($subtags as $subtag){
							if($failed){
								continue;
							}

							$subtag_match = false;

							# Trim subtag
							$subtag = trim($subtag);

							$object = $content_element;
							$i = 0;
							while($object->parent() != null){
								if(!($i++ == 0 && $is_general_object)){
									$object = $object->parent();
								}

								# Does the subtag match with the object?
								if(preg_match('/^(' . $object->tag . ')?(#' . $object->id . ')?(\.' . $object->class . ')?$/',$subtag,$object_matches) && count((isset($object_matches[1]) ? $object_matches[1] : '')) + count((isset($object_matches[2]) ? $object_matches[2] : '')) > 0){
									$subtag_match = true;
									break;
								}
							}

							if($subtag_match == false){
								$failed = true;
							}
						}

						if(!$failed){
							if($is_general_object){
								if(!isset($editor_css_parts[$general_object])){
									$editor_css_parts[$general_object] = '';
								}
								$editor_css_parts[$general_object] .= $part_css;
							}else{
								$editor_css_parts['body'] .= $part_css;
							}
						}
					}else{
						# Does the tag match with the object?
						$is_general_object = (bool) in_array(trim(preg_replace('/(\.[a-z0-9_\-]+)/i','',$tag)),$general_objects);

						if($is_general_object || (preg_match('/^(' . $object_tag . ')?(#' . $object_id . ')?(\.' . $object_class . ')?$/i',$tag,$object_matches) && count((isset($object_matches[1]) ? $object_matches[1] : '')) + count((isset($object_matches[2]) ? $object_matches[2] : '')) > 0)){
							$tagname = ($is_general_object ? $tag : 'body');
							if(!isset($editor_css_parts[$tagname])){
								$editor_css_parts[$tagname] = '';
							}
							$editor_css_parts[$tagname] .= $part_css;
						}
					}
				}
			}

			# Parse editor CSS parts
			$editor_css = '';

			foreach($editor_css_parts as $tagname => $content){
				$editor_css .= $tagname . " {\n";
				$items = explode(';',$content);
				krsort($items);
				$done = array();
				$items_placed = array();

				foreach($items as $item){
					# Remove background images
					if(preg_match('/background[^:]*:/ism',$item)){
						$item = preg_replace(array('/url\([^\)]*\)/ism','/(no-repeat|repeat|repeat-y|repeat-x)/ism','/(transparent)/ism','/(top|left|bottom|right)/ism','/[0-9]+px/ism'),'',$item);
					}

					# Trim
					$item = trim($item);

					if($item == '' || strpos($item,':') === (strlen($item)-1)){
						continue;
					}else{
						$name = substr($item,0,strpos($item,':'));
					}
					if(in_array($name,$done)){
						continue;
					}

					if($tagname == 'body'){
						$allowed = false;
						foreach($allowed_properties as $property){
							if(substr($name,0,strlen($property)) == $property){
								$allowed = true;
							}
						}

						if($allowed){
							$items_placed[] = $item;
							$done[] = $name;
						}
					}else{
						$items_placed[] = $item;
						$done[] = $name;
					}
				}

				krsort($items_placed);

				foreach($items_placed as $item){
					$editor_css .= $item . ";\n";
				}
				$editor_css .= "}\n";
			}

			# Remove empty tags
			$editor_css = preg_replace('/;[^:]+:\s*;/ism',';',$editor_css);

			return $editor_css;
		}


		/**
		 * Load the internal link popup
		 *
		 */
		private function loadInternalLink(){
			ob_end_clean();
			header('Content-type: text/json; charset=' . CHARSET);
			echo '[';

			$files = $this->pageOverview(true);
			$files_sorted = array();

			foreach($files as $filename => $regions){
				$files_sorted[$filename] = substr_count($filename,'/') . '-' . $filename;
			}
			natsort($files_sorted);

			foreach($files_sorted as $filename => $hash){
				echo '{value:"' . (str_repeat('../',substr_count($_GET['page_filename'],'/')) != '' ? str_repeat('../',substr_count($_GET['page_filename'],'/')) : './') . substr($filename,strlen(ROOT_DIRECTORY)) . '", title: "' . substr($filename,strlen(ROOT_DIRECTORY)) . '"},';
			}

			echo ']';
			exit;
		}


		/**
		 * Load the file manager popup (assetmanager)
		 *
		 */
		private function loadFileManager(){
			# Filter
			if(isset($_GET['ffilter']) && $_GET['ffilter'] == 'image'){
				$filter = $_GET['ffilter'];
			}else{
				$filter = '*';
			}

			if(!isset($_GET['directory'])){
				$directory_set = false;
				$_GET['directory'] = '';
			}elseif($_GET['directory'] == ''){
				$directory_set = true;
			}else{
				$directory_set = true;
				if(strpos($_GET['directory'],'../') !== false){
					$_GET['directory'] = '';
				}
				if(substr($_GET['directory'],0,1) == '/'){
					$_GET['directory'] = substr($_GET['directory'],1);
				}
				if(substr($_GET['directory'],-1) != '/'){
					$_GET['directory'] = $_GET['directory'] . '/';
				}
			}

			# extensions
			$image_extensions = array(
									'jpg' => '<i class="fa fa-file-image-o"></i>',
									'jpeg' => '<i class="fa fa-file-image-o"></i>',
									'gif' => '<i class="fa fa-file-image-o"></i>',
									'png' => '<i class="fa fa-file-image-o"></i>',
									'bmp' => '<i class="fa fa-file-image-o"></i>',
									'jpe' => '<i class="fa fa-file-image-o"></i>',
									'tif' => '<i class="fa fa-file-image-o"></i>'
								);
			$file_extensions = array(
									'avi' => '<i class="fa fa-file-video-o"></i>',
									'mov' => '<i class="fa fa-file-video-o"></i>',
									'mpg' => '<i class="fa fa-file-video-o"></i>',
									'mpeg' => '<i class="fa fa-file-video-o"></i>',
									'rm' => '<i class="fa fa-file-video-o"></i>',
									'xls' => '<i class="fa fa-file-excel-o"></i>',
									'csv' => '<i class="fa fa-file-excel-o"></i>',
									'pdf' => '<i class="fa fa-file-pdf-o"></i>',
									'php' => '<i class="fa fa-file-code-o"></i>',
									'php3' => '<i class="fa fa-file-code-o"></i>',
									'php4' => '<i class="fa fa-file-code-o"></i>',
									'php5' => '<i class="fa fa-file-code-o"></i>',
									'phtml' => '<i class="fa fa-file-code-o"></i>',
									'phtm' => '<i class="fa fa-file-code-o"></i>',
									'htm' => '<i class="fa fa-file-o"></i>',
									'html' => '<i class="fa fa-file-o"></i>',
									'doc' => '<i class="fa fa-file-word-o"></i>',
									'js' => '<i class="fa fa-file-code-o"></i>',
									'css' => '<i class="fa fa-file-code-o"></i>',
									'mp3' => '<i class="fa fa-file-audio-o"></i>',
									'mp4' => '<i class="fa fa-file-audio-o"></i>',
									'wav' => '<i class="fa fa-file-audio-o"></i>',
									'zip' => '<i class="fa fa-file-archive-o"></i>',
									'rar' => '<i class="fa fa-file-archive-o"></i>',
									'tar' => '<i class="fa fa-file-archive-o"></i>',
									'gz' => '<i class="fa fa-file-archive-o"></i>',
									'unknown' => '<i class="fa fa-file-o"></i>'
						  		);
			$extensions = array_merge($image_extensions, $file_extensions);

			if(isset($_GET['subaction']) && $_GET['subaction'] == 'delete' && isset($_GET['filename']) && strpos($_GET['filename'],'/') === false){
				# Demo mode
				if(DEMO_MODE){
					header('Location: ./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $_GET['directory'] . '&demo=1');
					exit;
				}else{
					$this->fileDelete(UPLOAD_DIRECTORY . $_GET['directory'] . $_GET['filename']);
					header('Location: ./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $_GET['directory']);
					exit;
				}
			}elseif(isset($_GET['subaction']) && $_GET['subaction'] == 'upload' && isset($_FILES['file']['name']) && $_FILES['file']['name'] != ''){
				$filename = str_replace(array(' ','---','--','.jpeg'),array('-','-','-','.jpg'),strtolower($_FILES['file']['name']));
				$ascii = strpos($_FILES['file']['type'],'text') ? 1 : 0;

				# extension
				if(strpos($filename,'.') !== false){
					$extension = strtolower(substr($filename,strrpos($filename,'.')+1));
				}else{
					$extension = '';
				}

				# Unique filename
				$i = 0;
				while(file_exists(ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory'] . $filename)){
					$filename = substr($filename,0,-strlen($extension)-1-($i == 0 ? 0 : strlen($i)+1)) . '-' . ++$i . '.' .$extension;
				}

				# Is it an image? Yes: resize! (if too big)
				if(array_key_exists($extension,$image_extensions) && is_numeric($_POST['resize'])){
					# Extension
					if(strpos($filename,'.') !== false){
						$extension = strtolower(substr($filename,strrpos($filename,'.')+1));
					}else{
						$extension = '';
					}
					$content = $this->resizeImage($_FILES['file']['tmp_name'],$_POST['resize'],$_POST['resize'],true,$extension);
				}else{
					$content = file_get_contents($_FILES['file']['tmp_name']);
					if(ini_get('magic_quotes_runtime')){
						$content = stripslashes($content);
					}
				}

				# Demo mode
				if(DEMO_MODE){
					header('Location: ./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&demo=1');
					exit;
				}else{
					# Upload
					$this->filePutContents(UPLOAD_DIRECTORY . $_GET['directory'] . $filename,$content,$ascii);
						header('Location: ./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $_GET['directory'] . '&selected_filename=' . $filename);
					exit;
				}
			}

			if(isset($_GET['selected_filename'])){
				if(strpos($_GET['selected_filename'],'.') !== false){
					$selected_extension = strtolower(substr($_GET['selected_filename'],strrpos($_GET['selected_filename'],'.')+1));
				}else{
					$selected_extension = '';
				}
			}

			//alternative substr
			function truncate($string, $length = '', $replacement = ' ..', $start = 20) {
			    if  (strlen ($string) <= $start)
			        return  $string;
			    if  ($length) {
			        return  substr_replace ($string, $replacement, $start, $length);
			    } else  {
			        return  substr_replace ($string, $replacement, $start);
			    }
			}
			echo '
			<!DOCTYPE html>
			<html lang="en">
			<head>
			<meta charset="utf-8">
			<title>' . $this->getLanguage('file_manager') . '</title>
			<base target="_self" />
			<script type="text/javascript">
				var bOk = false;
				function selectFile(filename, extension){
					var image_extensions = "jpg,jpe,jpeg,gif,png,tif,bmp,";
					if(image_extensions.indexOf(extension + ",") > -1){
						document.getElementById("preview").innerHTML = \'<img src="' . WEBSITE_URL . UPLOAD_DIRECTORY . $_GET['directory'] . '\' + filename + \'?time=' . time() . '" alt="\' + filename + \'" />\';
					}else{
						document.getElementById("preview").innerHTML = \'<span id="no_preview">' . $this->getLanguage('there_is_no_image_preview_possible') . '</span>\';
					}
					document.getElementById("preview_top").innerHTML = "<strong>' . $this->getLanguage('current_file') . ':</strong> <span id=\"filename\">" + filename.substring(0,35) + "</span>";
				}

				function doOk(){
					if(document.getElementById("filename") == null || document.getElementById("filename") == "undefined"){
						alert("' . $this->getLanguage('you_have_to_select_a_file_first') . '");
						return;
					}

					var filename = document.getElementById("filename");
					parent.tinymce.activeEditor.windowManager.getParams().setUrl("' . (str_repeat('../',substr_count($_GET['page_filename'],'/')) != '' ? str_repeat('../',substr_count($_GET['page_filename'],'/')) : './') . UPLOAD_DIRECTORY . $_GET['directory'] . '" + filename.innerHTML);
					parent.tinymce.activeEditor.windowManager.close();
					bOk = true;
					parent.tinymce.activeEditor.windowManager.close();

				}
				function doUnload(){
					/*
					if(navigator.appName.indexOf("Microsoft") != -1){
						if(!bOk)window.returnValue="";
					}else{
						if(!bOk)(opener?opener:openerWin).setAssetValue("");
					}
					*/
				}
				function deleteFile(message,filename){
					if(confirm(message)){
						var url_form = document.getElementById("url_form");
						url_form.action = "./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $_GET['directory'] . '&subaction=delete&filename=" + filename;
						url_form.submit();
					}
				}

				function getAssetValue(){
					var filename = parent.tinymce.activeEditor.windowManager.getParams().getUrl();
					if(filename == "" || filename.indexOf("' . UPLOAD_DIRECTORY . '") == -1){
		    			return;
		    		}
					var extension = filename;
					if(extension.indexOf("/") != -1){
						extension = extension.substring(extension.lastIndexOf("/"),extension.length);
					}
					extension = extension.substring(extension.indexOf(".")+1,extension.length);

					filename = filename.substring(filename.indexOf("' . UPLOAD_DIRECTORY . '")+' . strlen(UPLOAD_DIRECTORY) . ',filename.length);

					if(filename.indexOf("/") != -1){
						var directory = filename.substring(0,filename.lastIndexOf("/")+1);
						filename = filename.substring(directory.length,filename.length);
						document.location.href = "./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=" + directory + "&selected_filename=" + filename;
						return;
					}

					selectFile(filename,extension);
				}
			</script>

			<!-- Bootstrap Core CSS -->
			<link href="' . WEBSITE_URL . CMS_DIRECTORY . '/skins/css/bootstrap.min.css" rel="stylesheet">

			<!-- Custom Fonts -->
			<link href="' . WEBSITE_URL . CMS_DIRECTORY . '/skins/font-awesome-4.1.0/css/font-awesome.min.css" rel="stylesheet" type="text/css">
			<link rel="stylesheet" type="text/css" href="' . WEBSITE_URL . CMS_DIRECTORY . '/css/file_manager.css" />
			</head>
			<body onunload="doUnload()"' . (!$directory_set && (!isset($_GET['selected_filename']) || $_GET['selected_filename'] == '') ? ' onload="setTimeout(\'getAssetValue();\',\'100\');"' : '') . '>
			<form name="url_form" id="url_form" method="post" action=""></form>
				<div id="preview_top">' . (isset($_GET['selected_filename']) ? '<strong>' . $this->getLanguage('current_file') . ':</strong> <span id="filename">' . $_GET['selected_filename'] . '</span>' : $this->getLanguage('no_file_selected_yet')) . '</div>
				<div id="filelist_top"><strong>' . $this->getLanguage('filelist') . '</strong></div>
				<div id="preview">' . (isset($_GET['selected_filename']) && array_key_exists($selected_extension,$image_extensions) ? '<img src="' . WEBSITE_URL . UPLOAD_DIRECTORY . $_GET['directory'] . $_GET['selected_filename'] . '?time=' . time() . '" alt="' . $_GET['directory'] . $_GET['selected_filename'] . '" />' : '<span id="no_preview">' . (isset($_GET['demo']) && $_GET['demo'] == '1' ? $this->getLanguage('the_changes_have_not_been_saved_because_of_the_demo_mode') : $this->getLanguage('there_is_no_image_preview')) . '</span>') . '</div>
				<div id="filelist">
				<table width="100%">';

			# Read the files
			$exclude_directories = (EXCLUDE_DIRECTORIES == '' ? array() : explode(',',EXCLUDE_DIRECTORIES));
			if(!is_dir(ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory']) || (trim($_GET['directory']) != '' && in_array((strpos($_GET['directory'],'/') !== false ? substr($_GET['directory'],0,strpos($_GET['directory'],'/')) : $_GET['directory']),$exclude_directories))){
				header('Location: ./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=&selected_filename=');
				exit;
			}
			$handle = opendir(ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory']);
			$files = array();
			$directories = array();
			while($filename = readdir($handle)){
				if($filename != '.' && $filename != '..'){
					if(is_dir(ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory'] . $filename)){
						if(!in_array($_GET['directory'] . $filename,$exclude_directories)){
							$directories[$filename] = ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory'] . $filename;
						}
					}else{
						$files[$filename] = ROOT_DIRECTORY . UPLOAD_DIRECTORY . $_GET['directory'] . $filename;
					}
				}
			}

			# Sort the files and directories
			natcasesort($directories);
			natcasesort($files);

			# Show the directories
			if($_GET['directory'] != ''){
				$directory_up = $_GET['directory'];
				$directory_up = substr($directory_up,0,strrpos(substr($directory_up,0,-1),'/'));
				if($directory_up != '' && substr($directory_up,-1) != '/'){
					$directory_up .= '/';
				}

				echo '	<tr>
							<td style="width: 20px;"><i class="fa fa-folder" title="' . $this->getLanguage('directory_up') . '"></i></td>
							<td><a href="./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $directory_up . '">' . $this->getLanguage('directory_up') . '</a></td>
							<td style="width: 50px;"></td>
							<td style="width: 20px;"></td>
						</tr>';
			}
			foreach($directories as $name => $filename){
				$name_js = str_replace("'","\'",$name);
				echo '	<tr>
							<td style="width: 20px;"><i class="fa fa-folder"></i></td>
							<td><a href="./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&ffilter=' . $filter . '&directory=' . $_GET['directory'] . $name . '/">' . $name . '</a></td>
							<td style="width: 50px;"></td>
							<td style="width: 20px;"></td>
						</tr>';
			}

			# Show the files
			foreach($files as $name => $filename){
				# extension / Filter
				if(strpos($name,'.') !== false){
					$extension = strtolower(substr($name,strrpos($name,'.')+1));

					if($filter == 'image' && !array_key_exists($extension,$image_extensions)){
						continue;
					}

					if(!array_key_exists($extension,$extensions)){
						$extension = 'unknown';
					}
				}else{
					$extension = 'unknown';
				}

				# Filesize
				$size = filesize($filename);
				if($size/1024/1024/1024 > 1){
					$filesize = round($size/1024/1024/1024,2) . ' GB';
				}elseif($size/1024/1024 > 1){
					$filesize = round($size/1024/1024,2) . ' MB';
				}elseif($size/1024 > 1){
					$filesize = round($size/1024,1) . ' kB';
				}else{
					$filesize = round($size) . ' bytes';
				}

				$name_js = str_replace("'","\'",$name);
				echo '	<tr>
							<td style="width: 20px;">' . $extensions[$extension] . '</td>
							<td><a href="#" onclick="selectFile(\'' . $name_js . '\',\'' . $extension . '\');" title="' . $name . '">' . truncate($name) . '</a></td>
							<td style="width: 80px;">' . $filesize . '</td>
							<td style="width: 20px;"><a href="#" onclick="deleteFile(\'' . strip_tags($this->getLanguage('are_you_sure_you_want_to_delete',$name_js)) . '\',\'' . $name_js . '\');"><i class="fa fa-trash-o" title="' . $this->getLanguage('delete') . '"></i></a></td>
						</tr>';
			}

			echo '</table>
			</div>
			<div id="upload">
				<b>' . $this->getLanguage('upload_new_file') . '</b><br />
				<form name="upload" action="./index.php?page=file_manager&page_filename=' . urlencode($_GET['page_filename']) . '&subaction=upload&ffilter=' . $filter . '&directory=' . $_GET['directory'] . '" method="post" enctype="multipart/form-data">
					<input type="file" name="file" size="35" />
					<button class="uploadbutton btn btn-primary" type="submit" name="submit">' . $this->getLanguage('upload') . '</button><br />
					' . $this->getLanguage('resize_images') . ': <input type="radio" name="resize" value="640" /> ' . $this->getLanguage('large') . ' (640) &nbsp; <input type="radio" name="resize" value="340" checked="checked" /> ' . $this->getLanguage('medium') . ' (340) &nbsp; <input type="radio" name="resize" value="150" /> ' . $this->getLanguage('small') . ' (150) &nbsp; <input type="radio" name="resize" value="" /> ' . $this->getLanguage('none') . '
				</form>
			</div>

			<div id="buttons">
	            <button class="btn btn-primary btn-editor" type="button" name="cancel" onclick="parent.tinymce.activeEditor.windowManager.close();">' . strtolower($this->getLanguage('cancel')) . '</button>
				<button class="btn btn-success btn-editor" type="submit" name="ok" onclick="doOk();">' . strtolower($this->getLanguage('ok')) . '</button>
			</div>
		</body>
		</html>';
		}

    	/**
    	 * Resize an image (handler)
    	 *
    	 * @param resource/string $image
    	 * @param int $max_width
    	 * @param int $max_height
    	 * @param boolean $return_content = true
    	 * @param string  $extension = null
    	 * @param boolean $crop = false
    	 * @return string $new_image
    	 */
    	public function resizeImage($image, $max_width, $max_height, $return_content = true, $extension = null, $crop = false){
			if($extension == null && !is_resource($image) && strpos($image,'.') !== false){
				$extension = substr($image,strrpos($image,'.')+1);
			}
			$extension = str_replace('jpeg','jpg',strtolower($extension));

    		if(is_resource($image)){
    			$old_image = $image;
    		}else{
    			if($extension == 'png'){
    				$old_image = imagecreatefrompng($image);
    			}elseif($extension == 'bmp'){
    				$old_image = imagecreatefromwbmp($image);
    			}elseif($extension == 'gif'){
    				$old_image = imagecreatefromgif($image);
    			}elseif($extension == 'jpg'){
    				$old_image = imagecreatefromjpeg($image);
    			}else{
    				$extension = 'jpg';
    				$old_image = imagecreatefromstring(file_get_contents($image));
    			}
    		}

    		$image_x = imagesx($old_image);
    		$image_y = imagesy($old_image);
			$scale_x = round($max_width / $image_x,3);
			$scale_y = round($max_height / $image_y,3);

			# If the image is already small enough
			if($image_x <= $max_width && $image_y <= $max_height){
		        # Transparent
		        if($extension == 'png'){
					imagealphablending($old_image, true);
		        	$new_image = imagecreatetruecolor($image_x, $image_y);
					imagealphablending($new_image, false);
					imagesavealpha($new_image, true);

					imagecopyresampled($new_image, $old_image, 0, 0, 0, 0, $image_x, $image_y, $image_x, $image_y);
		        }elseif($extension == 'gif' && $return_content && !is_resource($image)){
					return file_get_contents($image);
		        }else{
					$new_image = $old_image;
		        }

				# Get content of new image
				if($return_content){
					ob_start();

					if($extension == 'png'){
						imagepng($new_image);
					}elseif($extension == 'bmp'){
						imagewbmp($new_image);
					}elseif($extension == 'gif'){
						imagegif($new_image);
					}elseif(is_resource($image)){
						imagejpeg($new_image,null,100);
					}else{
						echo file_get_contents($image);
					}

					$new_image = ob_get_contents();
					ob_end_clean();
				}

				return $new_image;
			}else{
				# Determine new dimensions
				$new_image_start_x = 0;
				$new_image_start_y = 0;
				if($crop){
					$new_image_x = $max_width;
					$new_image_y = $max_height;

					if($scale_x > $scale_y){
						$new_image_start_y = (($scale_x * $image_y) - $max_height) / $scale_x / 2;
					}else{
						$new_image_start_x = (($scale_y * $image_x) - $max_width) / $scale_y / 2;
					}
				}else{
					if($scale_x > $scale_y){
						$new_image_y = $max_height;
						$new_image_x = ($scale_y * $image_x);
					}else{
						$new_image_y = ($scale_x * $image_y);
						$new_image_x = $max_width;
					}
				}

				# Create resized image
		        $new_image = imagecreatetruecolor($new_image_x, $new_image_y);

		        # Transparent
		        if($extension == 'png'){
					imagealphablending($old_image, true);
					imagealphablending($new_image, false);
					imagesavealpha($new_image, true);
					$transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
					imagefilledrectangle($new_image, 0, 0, $new_image_x, $new_image_y, $transparent);
		        }elseif($extension == 'gif'){
					$transparent_index = imagecolortransparent($old_image);
					imagepalettecopy($old_image, $new_image);
					imagefill($new_image, 0, 0, $transparent_index);
					imagecolortransparent($new_image, $transparent_index);
					imagetruecolortopalette($new_image, true, 256);
				}

		        # New image
		        imagecopyresampled($new_image, $old_image, 0, 0, $new_image_start_x, $new_image_start_y, $new_image_x, $new_image_y, $image_x-($new_image_start_x*2), $image_y-($new_image_start_y*2));
			}

	        # Get content of new image
	        if($return_content){
		        ob_start();

		        if($extension == 'png'){
		        	imagepng($new_image);
		        }elseif($extension == 'bmp'){
		        	imagewbmp($new_image);
		        }elseif($extension == 'gif'){
		        	imagegif($new_image);
		        }else{
		        	imagejpeg($new_image,null,75);
		        }

		        $new_image = ob_get_contents();
		        ob_end_clean();
	        }

	    	return $new_image;
    	}


		/**
		 * Return the content of an URL
		 *
		 * @param string $url
		 * @return string
		 */
		private function urlGetContents($url){
			if(function_exists('curl_init') && function_exists('curl_setopt') && function_exists('curl_exec') && function_exists('curl_exec')){
				# Use cURL
				$curl = curl_init($url);

				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_HEADER, 0);
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
				curl_setopt($curl, CURLOPT_TIMEOUT, 5);
				if(stripos($url,'https:') !== false){
					curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
					curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
				}

				$content = curl_exec($curl);
				if(ini_get('magic_quotes_runtime')){
					$content = stripslashes($content);
				}
				curl_close($curl);
			}else{
				# Use FGC, because cURL is not supported
				ini_set('default_socket_timeout',5);
				$content = file_get_contents($url);
				if(ini_get('magic_quotes_runtime')){
					$content = stripslashes($content);
				}
			}
			return $content;
		}
	 }

	 new FLATCMS();
?>
