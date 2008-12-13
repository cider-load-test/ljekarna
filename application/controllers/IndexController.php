<?php

class IndexController extends Zend_Controller_Action
{
	function init()
	{
		$this->initView();
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$pages = new Pages();		
	}
	
	function indexAction()
	{
		$this->_helper->layout->setLayout('frontpage'); 
		$this->view->title = "Dobrodošli";
	}
}