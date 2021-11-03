<?php
require __DIR__ . '/ForceApiSubscriptionQuery.php';

use Clickpdx\Salesforce\ForceApiSubscriptionQuery;

/**
 * WhitelistedNamespaces
 * Author:  Lisa Ridley
 * Date:  25 Feb 2010
 * Version 0.85 beta
 * Copyright (C) 2010 Lisa Ridley
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You can find a copy of the GNU General Public License at http://www.gnu.org/copyleft/gpl.html
 * A paper copy can be obtained by writing to:  Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * To install this extension, save this file in the "extensions" folder of your
 *   MediaWiki installation, and add the following to LocalSettings.php
 *
 *   $wgGroupPermissions['*']['read'] = false;
 *   require_once("$IP/extensions/WhitelistedNamespaces.php");
 *   $wgWhitelistedNamespaces = array(NS_MAIN, NS_TALK);
 *
 *
 */
 
define('USER_HAS_ACCESS',true);
define('USER_NO_ACCESS',false);
 
$wgExtensionCredits['other'][] = array(
    'name' => 'WhitelistedNamespaces',
    'author' => 'Lisa Ridley',
    'url' => 'http://www.mediawiki.org/wiki/Extension:WhitelistedNamespaces',
    'version' => '0.85 beta',
    'description' => 'Allows for whitelisting of all pages in a particular namespace or set of namespaces',
);
 
$wgExtensionFunctions[] = 'fnWhitelistedNamespaceSetup';
 

 
 
/**
 * By default, we restrict access to reading articles
 *  Set *-read to "false";
 *   However, allow access for the bot.
 */
function fnWhitelistedNamespaceSetup(){
	global $wgHooks, $wgGroupPermissions, $wgAutoloadClasses;
	
	$wgGroupPermissions['*']['read'] = false;	
	
	if(strpos($_SERVER['HTTP_USER_AGENT'],'Appserver') !== false) {
		$wgGroupPermissions['*']['read'] = true;
	}
	
	$wgHooks['UserGetRights'][] = 'fnWhitelistedNamespaces';
	
	$wgAutoloadClasses['SalesforceGrantManager'] = 'extensions/WhitelistedNamespaces/classes/SalesforceGrantManager.php';
}


/**
 * Adds currently viewed page to $wgWhitelistRead if page is in whitelisted namespace
 * Always returns true so that other extensions using the UserGetRights hook
 * will be executed
 *
 * @params $user User object
 * @params $rights array of user rights
 * @return boolean true
 */
function fnWhitelistedNamespaces($user, $rights)
{

	if(strpos($_SERVER['HTTP_USER_AGENT'],'Appserver') !== false) {
		return USER_HAS_ACCESS;
	}

	if(defined('DO_MAINTENANCE')) return true;
	
	global $wgOcdlaSessionDBtype, $wgOcdlaSessionDBserver, $wgOcdlaSessionDBname, $wgOcdlaSessionDBuser, $wgOcdlaSessionDBpassword;
	
	
  global $wgWhitelistedNamespaces, 
  
	$wgOcdlaBooksOnlineNamespaces,
	
	$wgWebStoreDomain,
  
  $wgTitle, 
  
  $wgWhitelistRead,
  
  $wgServer,
  
  $wgSsoSession,
  
  $wgAuthOcdla_LoginURL,
  
  // $wgOut,
  
  $wgWhitelistedNamespacesOrderLineQuery,
  $wgWhitelistedNamespacesFreeTrialQuery,
  $wgWhitelistedNamespacesSubscriptionQuery,
  
	$wgOcdlaBonStoreLink;
	

	
	// print_r($user->samlUid);exit;

	$retURL = urlencode( $wgServer . '/'.$wgTitle);

	$LoginPage = $wgAuthOcdla_LoginURL .'?retURL='.$retURL;

	$action = isset($_GET['action']) ? $_GET['action'] : 'view';
	
	//	$isNewPage ()
	// This is 100, 102, etc
	$namespace 	= $wgTitle->getNamespace();
	
	// This is "Mental_Health_Manual", etc.
	$nstext 		= $wgTitle->getNsText();
	
	$title 			= $wgTitle->getFullText();

	$login = "<a href='{$LoginPage}'>Login</a>";


//	var_dump($wgTitle);exit;

	// print $wgTitle; exit;
	
	// Check to see if namespace of current title is in whitelisted namespaces
	if(in_array($namespace, $wgWhitelistedNamespaces))
	{
		//build title with prefix
		$titletoadd = $title;
		//check to see if title is in whitelist
		if(is_array($wgWhitelistRead))
		{
			if(!in_array($titletoadd, $wgWhitelistRead))
			{
				//add if not in whitelist
				$wgWhitelistRead[] = $titletoadd;
			}
		}
		else
		{
			$wgWhitelistRead = array($titletoadd);
		}
	}



	if(strpos($_SERVER['HTTP_USER_AGENT'],'Appserver') !== false) {
		return USER_HAS_ACCESS;
	}
	
	ttail('This page is: '.$title, 'whitelist');
	ttail('Whitelisted data is: ', 'whitelist', $wgWhitelistRead);
	
	// Begin checking if this page is "Read-able" by the current user.
	// White-listed pages will take precedence so bail (return true) 
	// if this page has been White-listed.
	
	if(in_array($title, $wgWhitelistRead))
	{
		return USER_HAS_ACCESS;
	}
	
	// If a Legcomm page then the user must have Legcomm rights
	else if($namespace == NS_LEGCOMM && !in_array('read-legcomm',$rights))
	{
		header("HTTP/1.1 401 Unauthorized" );
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");
		die("You don't have the appropriate permissions to access this document.  {$login} to view it.");
	}
	// Anonymous users can't view Books Online materials.
	else if(in_array($namespace,$wgOcdlaBooksOnlineNamespaces) && $user->getId() === 0 && $action != 'edit')
	{
		header("HTTP/1.1 401 Unauthorized" );
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");
		die("Guest users cannot view OCDLA Books Online publications.  {$login} to view it.");
	}
	else if(in_array($namespace,$wgOcdlaBooksOnlineNamespaces) && !in_array('read-subscriptions',$rights))
	{  
	

				
		try
		{
			$wgSsoSession = DatabaseBase::factory( 'ocdlasession',
				array(
					'host' 						=> $wgOcdlaSessionDBserver,
					'user' 						=> $wgOcdlaSessionDBuser,
					'password' 				=> $wgOcdlaSessionDBpassword,
					// Both 'dbname' and 'dbName' have been
					// used in different versions.
					'dbname' 					=> $wgOcdlaSessionDBname,
					'dbName' 					=> $wgOcdlaSessionDBname,
					// 'flags' 				=> $db_flags,
					// 'tablePrefix' 	=> $db_tableprefix,
				)
			);
			
			$contactId = $wgSsoSession->getContactId();
		
			$api = new ForceApiSubscriptionQuery;

			if($api->hasOnlineSubscriptionAccess($wgWhitelistedNamespacesOrderLineQuery,$contactId) || $api->hasOnlineSubscriptionAccess($wgWhitelistedNamespacesSubscriptionQuery,$contactId))
			{
				return USER_HAS_ACCESS;
			}
			
			
			$grantManager = new SalesforceGrantManager($api);
			$grantManager->setSalesforceQuery($wgWhitelistedNamespacesFreeTrialQuery);
			$grantManager->setContactId($contactId);
			$grantManager->doApi();
			
			// print $grantManager;exit;
			
			// Free Trial access
			if($grantManager->hasNamespaceAccess($namespace))
			{
				
				// print $grantManager;exit;
				$grantInfo = $grantManager->getGrantInfo($namespace);
				$productName = $grantInfo['PricebookEntry.Product2.Name'];
				$expiryDate = $grantInfo['ExpirationDate__c'];
				// $upgradeProductName = '';
				
				$user->ocdlaMessages[] = "We hope you're enjoying your {$productName}.  Your subscription will expire on {$expiryDate}.<br />Renew for a full year of <a href='{$wgOcdlaBonStoreLink}'>Books Online manuals here</a>.";
				
				if($namespace == NS_SSM){
					$user->ocdlaMessages = array("<span class='message-heading'>upgrade now!</span>OCDLA's Search & Seizure in Oregon was revised September, 2018.  The updated manual contains 90 additional pages of developments in search and seizure case law with analysis. For complete access to the latest Search & Seizure updates, <a href='{$wgOcdlaBonStoreLink}' target='_new'>become a full subscriber</a>.");
				}
				
				return USER_HAS_ACCESS;
			}
			
			else
			{
				header("HTTP/1.1 401 Unauthorized" );
				header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");
				if($user->isAnon())
				{
					die("You don't have the appropriate permissions to access this document.  {$login} to view it.");
				}
				else
				{
					die("To view this document please purchase the <a href='".$wgOcdlaBonStoreLink."'>OCDLA Online Books subscription</a>.");
				}
			}
		}
		catch(\Exception $e)
		{
			header("HTTP/1.1 401 Unauthorized" );
			header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
			header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");
			die("There was an error determining your access to this document: {$e->getMessage()}.");
		}
	}
	else if(in_array($namespace,$wgOcdlaBooksOnlineNamespaces) && $action=='edit'
		&& !in_array('edit-subscriptions',$rights))
	{
		header("HTTP/1.1 401 Unauthorized" );
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header("Cache-Control: no-cache, max-age=0, must-revalidate, no-store");
		die("You don't have the appropriate permissions to access this document.  {$login} to view it.");
	}
	
	

	// Page is not whitelisted and user doesn't have a valid session
	else if(false && $namespace < NS_BLOG && $user->isAnon())
	{
		// print 'exiting...';exit;
		/* print "<pre>";
		$_SERVER['HTTP_HOST'] = 'auth.ocdla.org';
		$_SERVER['SERVER_NAME'] = 'auth.ocdla.org';
		print_r($_SERVER);
		print "</pre>";

		header("Host: auth.ocdla.org", true);
		print_r(getallheaders());
		*/
		header("Location: {$LoginPage}", true, 301);
		exit;
	}

	// Otherwise, public users can view this page
  return USER_HAS_ACCESS; 
}