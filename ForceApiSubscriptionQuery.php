<?php

namespace Clickpdx\Salesforce;

use Clickpdx\OAuth\OAuthGrantTypes;
use Clickpdx\SfRestApiRequestTypes;
use Clickpdx\Http\HttpRequest;
use Clickpdx\ResourceLoader;
use RestApiAuthenticationException;
use RestApiInvalidUrlException;

class ForceApiSubscriptionQuery
{

	 	
	private $debug = array();
	
	const CONTACT_QUERY = "SELECT Id, FirstName, LastName, MiddleName,
	Ocdla_Organization__c,
	MailingStreet, MailingCity, MailingState, MailingPostalCode, Ocdla_Publish_Mailing_Address__c,
	Ocdla_Bar_Number__c,
	Ocdla_Areas_of_Interest_1__c, Ocdla_Areas_of_Interest_2__c, Ocdla_Areas_of_Interest_3__c, Ocdla_Areas_of_Interest_4__c, Ocdla_Areas_of_Interest_5__c,
	OrderApi__Work_Phone__c,
	MobilePhone,
	Fax,
	OrderApi__Work_Email__c,
	Ocdla_Publish_Work_Email__c,
	Ocdla_Website__c
	FROM Contact WHERE Id='%s'";
	

	private function getMessages($type = 'debug')
	{
		return $this->debug;
	}
	
	private function addMessage($msg, $type = 'debug')
	{
		$this->debug[] = $msg;
	}
	
	public function doApiRequest($query, $param1 = null, $param2 = null)
	{
		$oauth = ResourceLoader::getResource('sfOauth');
		$this->addMessage((string)$oauth);
		
		$this->addMessage("<h2>This is the oauth resource loader:</h2>");
		$this->addMessage("Resource is: ".get_class($oauth));
		
		$oauth_result = $oauth->authorize();
		$this->addMessage(entity_toString($oauth_result));

		
		$forceApi = ResourceLoader::getResource('forceApi',true);
		$forceApi->setDebug(false);
		$forceApi->setAuthenticationService($oauth);
		$forceApi->setInstanceUrl($oauth_result['instance_url']);
		$forceApi->setAccessToken($oauth_result['access_token']);

		$queryString = sprintf($query, $param1, $param2);
		// print $queryString; exit;
		
		$sfResult = $forceApi->executeQuery($queryString);
		
		return $sfResult;
	}
		
	public function getLineItem($contactId){}

	
	public function hasOnlineSubscriptionAccess($accessQuery,$contactId)
	{
		if('' == trim($contactId) || empty($contactId)) {
			return false;
		}
		try
		{
			$sfResult 		= 	$this->doApiRequest($accessQuery, $contactId);
			// $error 				= 	!$sfResult->count() ? 'No records found for this request.' : "";
			$results 			= 	$sfResult->fetchAll();
			// print_r($results);
			if(!$sfResult->count())
			{
				return false;
				// throw new \Exception('Access Denied: no valid purchase found.');
			}
		}
		/*
		catch(RestApiAuthenticationException $e)
		{
			$error = $e->getMessage();
		}
		catch(RestApiInvalidUrlException $e)
		{
			$error = $e->getMessage();
		}
		*/
		catch(Exception $e)
		{
			return false;
		}
		return true;
	}
	
	
	public function getSalesforceAccessGrants($accessQuery, $contactId)
	{
		// Any grants returned from this product query.
		// This method should return an array of grants that can be used throughout
		// the validation/permissioni process.
		$grants = array();
	
		if('' == trim($contactId) || empty($contactId)) {
			return false;
		}
		try
		{
			$sfResult 		= 	$this->doApiRequest($accessQuery, $contactId);
			// $error 				= 	!$sfResult->count() ? 'No records found for this request.' : "";
			$results 			= 	$sfResult->fetchAll();
			// print_r($results);
			if(!$sfResult->count())
			{
				return $grants;
				// throw new \Exception('Access Denied: no valid purchase found.');
			}
		}
		catch(Exception $e)
		{
			return $grants;
		}
		
	 	// print "<pre>".print_r($sfResult,true)."</pre>";
	 	
	 	foreach($sfResult as $result){
	 		$grants[] = array(
	 			'Grants__c' => $result['Grants__c'],
	 			'Id' => $result['Id'],
	 			'ExpirationDate__c' => $result['ExpirationDate__c'],
	 			'PricebookEntry.Product2.Name' => $result['PricebookEntry']['Product2']['Name']
	 		);
	 	}
	 	
	 	return $grants;
	}

	
}