<?php

require_once 'Zend/Controller/Action.php';

class ArticleController extends Zend_Controller_Action
{

	function init()
	{
		$this->view->baseUrl = $this->_request->getBaseUrl();
		$menu = new Menu();
		
		$ret = $menu->render("Glavni", $this->view->baseUrl);
		$this->view->topmenu = $ret->topmenu;
		$this->view->submenu = $ret->submenu;
		$this->view->menucounter = $ret->counter;
		
		$this->view->leftmenu = $menu->render("Lijevi", $this->view->baseUrl);
		
		$session = new Zend_Session_Namespace('Default');
	}
	
	public function commentAction()
	{
		if($this->getRequest()->isPost())
		{
			$articles = new Articles();
			$article = $articles->getById($this->_request->getParam("article_id"));
			if(($article->comments == 1) && ($this->_request->getParam("captcha") == $session->captchaResult))
			{
				require_once 'Zend/Date.php';
				$date = new Zend_Date(Zend_Date::now(), Zend_Date::ISO_8601);			
				$comments = new Comments();
				$params = $this->_request->getParams();
				$data = array(
				    'article_id'   	=> $this->_request->getParam("article_id"),
				    'fullname'		=> $this->_request->getParam("fullname"),
					'email'			=> $this->_request->getParam("email"),
				    'text'			=> stripslashes($this->_request->getParam("text")),
					'website'		=> $this->_request->getParam("website")
				);
				if($data['website'] == "http://") $data['website'] = ""; 
				$comments->insert($data);
			} 
		}
		$this->_redirect("student/article/" . $this->_request->getParam("slug"));
	}
	
	public function indexAction()
	{
		if($this->_getParam("slug") == "") $this->_redirect("/");
		$this->view->slug = $this->_getParam("slug");		
		$articles = new Articles();
		$this->view->article = $articles->getBySlug($this->view->slug, 1);
		$this->view->path = array(array("path" => "article/" . $this->view->article->slug, "title" => "Članak: " . $this->view->article->title));
		
		if($this->view->article->comments)
		{
			$a = rand(2, 9);
			$b = rand(2, 9);
			$op = rand(0, 1);
			if($op) 
			{
				$calc = "$a &#215; $b =";
				$result = $a * $b;
			}
			else
			{
				$calc = "$a + $b =";
				$result = $a + $b;
			}
			$this->view->calc = $calc;
			$this->view->result = $result;
			$session->captchaResult = $result;
		}
		
		if(!isset($this->view->article->error))
		{
			$comments = new Comments();
			$this->view->comments = $comments->get($this->view->article->id);
		}
	}
	
	public function archiveAction()
	{
		$this->view->category = $category = $this->_getParam("category", "Novosti");
		$articles = new Articles();	
		$this->view->path = array(array("path" => "clanak/arhiva/" . $category, "title" => "Arhiva " . $category));
		
		$this->view->articles = $articles->getArticles($category, 0, 0);
	}
}