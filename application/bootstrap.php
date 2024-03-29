<?php
// application/bootstrap.php
// 
// Step 1: APPLICATION CONSTANTS - Set the constants to use in this application.
// These constants are accessible throughout the application, even in ini 
// files. We optionally set APPLICATION_PATH here in case our entry point 
// isn't index.php (e.g., if required from our test suite or a script).
defined('APPLICATION_PATH')
    or define('APPLICATION_PATH', dirname(__FILE__));

defined('APPLICATION_ENVIRONMENT')
    or define('APPLICATION_ENVIRONMENT', 'development');

// Step 2: FRONT CONTROLLER - Get the front controller.
// The Zend_Front_Controller class implements the Singleton pattern, which is a
// design pattern used to ensure there is only one instance of
// Zend_Front_Controller created on each request.
$frontController = Zend_Controller_Front::getInstance();

// Step 3: CONTROLLER DIRECTORY SETUP - Point the front controller to your action
// controller directory.
$frontController->addModuleDirectory(APPLICATION_PATH . 'modules');
$frontController->setControllerDirectory(array(
											"default" => APPLICATION_PATH . "controllers",
											"admin" => APPLICATION_PATH . "modules/admin/controllers"
										));

// Step 4: APPLICATION ENVIRONMENT - Set the current environment.
// Set a variable in the front controller indicating the current environment --
// commonly one of development, staging, testing, production, but wholly
// dependent on your organization's and/or site's needs.
$frontController->setParam('env', APPLICATION_ENVIRONMENT);

// CONFIGURATION - Setup the configuration object
// The Zend_Config_Ini component will parse the ini file, and resolve all of
// the values for the given section.  Here we will be using the section name
// that corresponds to the APP's Environment
$configuration = new Zend_Config_Ini(
    APPLICATION_PATH . '/config/app.ini', 
    APPLICATION_ENVIRONMENT
);

// REGISTRY - setup the application registry
// An application registry allows the application to store application 
// necessary objects into a safe and consistent (non global) place for future 
// retrieval.  This allows the application to ensure that regardless of what 
// happends in the global scope, the registry will contain the objects it 
// needs.
$registry = Zend_Registry::getInstance();
$registry->configuration = $configuration;

// DATABASE ADAPTER - Setup the database adapter
// Zend_Db implements a factory interface that allows developers to pass in an 
// adapter name and some parameters that will create an appropriate database 
// adapter object.  In this instance, we will be using the values found in the 
// "database" section of the configuration obj.
$dbAdapter = Zend_Db::factory($configuration->db);
$dbAdapter->setFetchMode(Zend_Db::FETCH_OBJ);

// DATABASE TABLE SETUP - Setup the Database Table Adapter
// Since our application will be utilizing the Zend_Db_Table component, we need 
// to give it a default adapter that all table objects will be able to utilize 
// when sending queries to the db.
Zend_Db_Table_Abstract::setDefaultAdapter($dbAdapter);
$dbAdapter->query("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");

		
// REGISTRY - setup the application registry
// An application registry allows the application to store application 
// necessary objects into a safe and consistent (non global) place for future 
// retrieval.  This allows the application to ensure that regardless of what 
// happends in the global scope, the registry will contain the objects it 
// needs.
$registry = Zend_Registry::getInstance();
$registry->configuration = $configuration;
$registry->dbAdapter     = $dbAdapter;
Zend_Registry::set('db', $dbAdapter);

// učitaj strukturu stranice		
$pages = new Pages();
$paths = $pages->getAllPaths("glavni");	

foreach($paths as $curpath)
{			
	$route = new Zend_Controller_Router_Route(	"student/" . $curpath["path"] . '/',
												array(	'module' => 'student',
														'controller' => 'page', 
														'action' => 'index', 
														'id' => $curpath["id"]));
	$router->addRoute("student" . $curpath["slug"], $route);
}


// LAYOUT SETUP - Setup the layout component
// The Zend_Layout component implements a composite (or two-step-view) pattern
// With this call we are telling the component where to find the layouts scripts.
Zend_Layout::startMvc(APPLICATION_PATH . 'layouts/scripts');

// VIEW SETUP - Initialize properties of the view object
// The Zend_View component is used for rendering views. Here, we grab a "global" 
// view instance from the layout object, and specify the doctype we wish to 
// use. In this case, XHTML1 Strict.
$view = Zend_Layout::getMvcInstance()->getView();
$view->doctype('XHTML1_STRICT');
$view->setHelperPath(APPLICATION_PATH . 'views/helpers');

// Start session
Zend_Session::start();

// CLEANUP - remove items from global scope
// This will clear all our local boostrap variables from the global scope of 
// this script (and any scripts that called bootstrap).  This will enforce 
// object retrieval through the Applications's Registry
unset($frontController, $view, $configuration, $registry, $dbAdapter);
