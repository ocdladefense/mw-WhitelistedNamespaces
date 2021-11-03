<?php
class SalesforceGrantManager {

	const FORCE_GRANT_OBJECT = 'OrderItem';

	const FORCE_GRANT_FIELD = 'Grants__c';
	
	private $grants = array();
	
	private $nsGrants = array();
	
	private $grantNamespaces = array();
		
	private $salesforceApi;
		
	private $salesforceQuery;
	
	private $contactId;
		
		
	public function __construct($api) {
		$this->salesforceApi = $api;
	}
	
	
	public function setContactId($contactId){
		$this->contactId = $contactId;
	}
	
	
	public function setSalesforceQuery($query){
		$this->salesforceQuery = $query;
	}
	
	
	
	public function doApi(){
		$sfResult = $this->getSalesforceGrants();

		
		foreach($sfResult as &$result){
			$nsShortName = $result[SalesforceGrantManager::FORCE_GRANT_FIELD];
			$result['MediaWikiNamespace'] = constant($nsShortName);
			$this->grants[] = $result;
			$this->grantNamespaces[] = constant($nsShortName);
		}
	}


	public function getGrantInfo($namespace)
	{
		foreach($this->grants as $grant){
			if($namespace == $grant['MediaWikiNamespace']){
				return $grant;
			}
		}
		
		return array();
	}


	private function getGrantsAsMediaWikiNamespaces($grants)
	{
		foreach($grants as $nsShortName){
			$nsGrants[] = constant($nsShortName);
		}
	
		return $nsGrants;
	}



	private function getSalesforceGrants()
	{
		$grants = $this->salesforceApi->getSalesforceAccessGrants($this->salesforceQuery,$this->contactId);

		if(count($grants) < 1) return array();

		return $grants;
	}



	public function hasNamespaceAccess($namespace)
	{
		// print "<pre>". print_r($this->nsGrants,true)."</pre>";
		return in_array($namespace, $this->grantNamespaces);
	}		
	
	
	
	public function __toString()
	{
		return "<pre>". print_r($this->grants,true). "</pre>";
	}
			
}