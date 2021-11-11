<?php

require __DIR__ . '/ForceApiSubscriptionQuery.php';

use Clickpdx\Salesforce\ForceApiSubscriptionQuery;
 
define('USER_HAS_ACCESS',true);
define('USER_NO_ACCESS',false);
 
$wgExtensionCredits['other'][] = array(
    'name' => 'WhitelistedNamespaces',
    'author' => 'Trevor Uehlin',
    'url' => 'http://www.mediawiki.org/wiki/Extension:WhitelistedNamespaces',
    'version' => '1.0',
    'description' => 'Allows for whitelisting of all pages in a particular namespace or set of namespaces',
);
 
$wgExtensionFunctions[] = 'init';
 

# By default, we restrict access to reading articles by setting "read" to "false", however, allow access for the bot.
function init(){

	global $wgHooks, $wgGroupPermissions, $wgAutoloadClasses;
	
	$wgGroupPermissions['*']['read'] = false;	
	
	if(strpos($_SERVER['HTTP_USER_AGENT'],'Appserver') !== false) $wgGroupPermissions['*']['read'] = true;
	
	$wgHooks['UserGetRights'][] = 'determineAccess';
	
	$wgAutoloadClasses['SalesforceGrantManager'] = 'extensions/WhitelistedNamespaces/classes/SalesforceGrantManager.php';
}


# Adds currently viewed page to $wgWhitelistRead if page is in whitelisted namespace, or the user has special access to the page.
function determineAccess($user, $rights){

	if(strpos($_SERVER['HTTP_USER_AGENT'], 'Appserver') !== false) return USER_HAS_ACCESS;

	if(defined('DO_MAINTENANCE')) return true;

	# Custom globals
	global $wgOcdlaSessionDBtype, $wgOcdlaSessionDBserver, $wgOcdlaSessionDBname, $wgOcdlaSessionDBuser, $wgOcdlaSessionDBpassword, $wgWhitelistedNamespaces;
	global $wgOcdlaBooksOnlineNamespaces, $wgWebStoreDomain, $wgSsoSession, $wgAuthOcdla_LoginURL;
	global $wgWhitelistedNamespacesOrderLineQuery, $wgWhitelistedNamespacesFreeTrialQuery, $wgWhitelistedNamespacesSubscriptionQuery, $wgOcdlaBonStoreLink;

	# Framework globals
	global $wgTitle, $wgServer, $wgWhitelistRead;
	

	$retURL = urlencode( $wgServer . '/'.$wgTitle);

	$LoginPage = $wgAuthOcdla_LoginURL .'?retURL='.$retURL;

	$action = isset($_GET['action']) ? $_GET['action'] : 'view';
	
	$namespace = $wgTitle->getNamespace();
	
	$nstext = $wgTitle->getNsText();
	
	$title = $wgTitle->getFullText();

	$login = "<a href='{$LoginPage}'>Login</a>";

	
	# Check to see if namespace of current title is in whitelisted namespaces.  If it is, make the page public.
	if(isWhitelistedNamespace($namespace)) {

		addToWhitelistRead($title);
	}
	
	ttail('This page is: '.$title, 'whitelist');
	ttail('Whitelisted data is: ', 'whitelist', $wgWhitelistRead);
	
	# Begin checking if this page is "Read-able" by the current user.
	
	if(isWhitelistedPage($title)) return USER_HAS_ACCESS;
	
	if($namespace == NS_LEGCOMM && !in_array('read-legcomm',$rights)) {

		# The user must have legcomm rights to view legcomm pages.
		set401Headers();
		die("You don't have the appropriate permissions to access this document.  {$login} to view it.");

	} else if(in_array($namespace,$wgOcdlaBooksOnlineNamespaces) && $user->getId() === 0 && $action != 'edit') {

		#Anonymous users can't view Books Online materials.
		set401Headers();
		die("Guest users cannot view OCDLA Books Online publications.  {$login} to view it.");

	} else if(in_array($namespace,$wgOcdlaBooksOnlineNamespaces) && !in_array('read-subscriptions',$rights)) { 

		$dbCreds = array(
			'host' 	   => $wgOcdlaSessionDBserver,
			'user' 	   => $wgOcdlaSessionDBuser,
			'password' => $wgOcdlaSessionDBpassword,
			'dbname'   => $wgOcdlaSessionDBname,
			'dbName'   => $wgOcdlaSessionDBname
		);
	
		try {

			$wgSsoSession = DatabaseBase::factory( 'ocdlasession', $dbCreds);
			
			$contactId = $wgSsoSession->getContactId();
		
			$api = new ForceApiSubscriptionQuery(); // Not sure what this is?

			if($api->hasOnlineSubscriptionAccess($wgWhitelistedNamespacesOrderLineQuery,$contactId) || $api->hasOnlineSubscriptionAccess($wgWhitelistedNamespacesSubscriptionQuery,$contactId)) {
				
				return USER_HAS_ACCESS;
			}
			
			
			$grantManager = new SalesforceGrantManager($api);
			$grantManager->setSalesforceQuery($wgWhitelistedNamespacesFreeTrialQuery);
			$grantManager->setContactId($contactId);
			$grantManager->doApi();
			
			# Free Trial access
			if($grantManager->hasNamespaceAccess($namespace)) {
				
				$grantInfo = $grantManager->getGrantInfo($namespace);
				$productName = $grantInfo['PricebookEntry.Product2.Name'];
				$expiryDate = $grantInfo['ExpirationDate__c'];
				
				$user->ocdlaMessages[] = "We hope you're enjoying your {$productName}.  Your subscription will expire on {$expiryDate}.<br />Renew for a full year of <a href='{$wgOcdlaBonStoreLink}'>Books Online manuals here</a>.";
				
				if($namespace == NS_SSM) {

					$user->ocdlaMessages = array("<span class='message-heading'>upgrade now!</span>OCDLA's Search & Seizure in Oregon was revised September, 2018.  The updated manual contains 90 additional pages of developments in search and seizure case law with analysis. For complete access to the latest Search & Seizure updates, <a href='{$wgOcdlaBonStoreLink}' target='_new'>become a full subscriber</a>.");
				}
				
				return USER_HAS_ACCESS;

			} else {

				set401Headers();

				if($user->isAnon()) {

					die("You don't have the appropriate permissions to access this document.  {$login} to view it.");

				} else {

					die("To view this document please purchase the <a href='".$wgOcdlaBonStoreLink."'>OCDLA Online Books subscription</a>.");
				}
			}

		} catch(\Exception $e) {

			set401Headers();
			die("There was an error determining your access to this document: {$e->getMessage()}.");
		}

	} else if(in_array($namespace, $wgOcdlaBooksOnlineNamespaces) && $action == 'edit' && !in_array("edit-subscriptions", $right)) {

		set401Headers();
		die("You don't have the appropriate permissions to access this document.  {$login} to view it.");

	} else if(false && $namespace < NS_BLOG && $user->isAnon()) { // Page is not whitelisted and user doesn't have a valid session.

		header("Location: {$LoginPage}", true, 301);
		exit;
	}

  // Otherwise, public users can view this page
  return USER_HAS_ACCESS; 
}

function isWhitelistedNamespace($namespace) {

	global $wgWhitelistedNamespaces;

	return in_array($namespace, $wgWhitelistedNamespaces);
}

function isWhitelistedPage($title) {

	return in_array($title, $wgWhitelistRead);
}

function addToWhitelistRead($title) {

	global $wgWhitelistRead;

	if(is_array($wgWhitelistRead)) {

		if(!in_array($title, $wgWhitelistRead)) $wgWhitelistRead[] = $title;

	} else {

		$wgWhitelistRead = array($title);
	}
}

function set401Headers() {

	header("HTTP/1.1 401 Unauthorized" );
	header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
	header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");	
}