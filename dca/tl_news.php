<?php

$GLOBALS['TL_DCA']['tl_news']['config']['onsubmit_callback'][] = array('tl_news_pingback', 'Pingback');

class tl_news_pingback extends tl_news
{
	public function Pingback(DC_Table $DC)
	{
		$strActive = \Input::Post('published');
	
		if($strActive)
		{
			$arrUrls = $this->getUrlsFromContent($this->replaceInsertTags($this->getContent()));
			if($arrUrls)
			{
				$strSourceUrl = $this->frontendUrl();
				foreach($arrUrls as $strTargetUrl)
				{
					$strPingbackUrl = $this->getPingbackUrl($strTargetUrl);
					if($strPingbackUrl)
					{
						$strXml = $this->buildXML($strSourceUrl, $strTargetUrl);
						$strResponse = $this->request($strPingbackUrl, $strXml, false);
						$objResponse = @simplexml_load_string($strResponse);

						if($objResponse->fault)
						{
							$strMessage = $objResponse->fault->value->struct->member[1]->value->string;
							\System::log('[pingback] ' . $strMessage . ' (Target: ' . $strTargetUrl . ')', __METHOD__, TL_ERROR);
						}
						elseif($objResponse->params)
						{
							$strMessage = $objResponse->params->param->value->string;
							\System::log('[pingback] ' . $strMessage, __METHOD__, TL_GENERAL);
						}
						else
						{
							$strMessage = 'Foreign host ' . $strTargetHost . ' did not respond well-formed.';
							\System::log('[pingback] ' . $strMessage, __METHOD__, TL_ERROR);
						}
					}
					else
					{
						$strMessage = $strTargetUrl . ' is not a pingback-enabled resource.';
						\System::log('[pingback] ' . $strMessage, __METHOD__, TL_ERROR);
					}
				}
			}
		}
	}

	protected function frontendUrl()
	{
		$myBaseUrl = \Environment::Get('base');
		$objNews = \NewsModel::findByAlias(\Input::Post('alias'));
		$objArchive = \NewsArchiveModel::findById($objNews->pid);
		$objPage = \PageModel::findById($objArchive->jumpTo);

		return $myBaseUrl . ampersand($this->generateFrontendUrl($objPage->row(), '/' . ((!$GLOBALS['TL_CONFIG']['disableAlias'] && strlen($objNews->alias)) ? $objNews->alias : $objNews->id)));
	}

	private function getContent()
	{
		$objNews = \NewsModel::findByAlias(\Input::Post('alias'));
                $objCte  = \ContentModel::findPublishedByPidAndTable($objNews->id, 'tl_news');

                if($objCte !== null)
                {
                        while ($objCte->next())
                        {
                                $data .= $this->getContentElement($objCte->current(), $this->strColumn);
                        }
                }

		return $data;
	}

	private function getUrlsFromContent($strContent)
	{
		$arrMatches = array();
		preg_match_all("/href\s*=\s*[\"|\'](.*?)[\"|\']/i", $strContent, $arrMatches);

		return $arrMatches[1];
	}
    
	protected function request($strUrl, $data = false, $headers = false)
	{
		$objRequest = new Request();

		if($data)
		{
			$objRequest->data = $data;
		}

		$objRequest->send($strUrl);
		if($objRequest->hasError())
		{
			return $objRequest->error;
		}

		if($headers === true)
		{
			$arrHeaders = $objRequest->headers;
			return $arrHeaders;
		}
		else
		{
			$strResponse = $objRequest->response; 
		}

		return $strResponse;
	}

	protected function buildXML($strSourceUrl, $strTargetUrl)
	{
		$strXml  = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";
		$strXml .= "<methodCall>\n";
		$strXml .= "<methodName>pingback.ping</methodName>\n";
		$strXml .= "<params>\n";
		$strXml .= "<param><value><string>" . $strSourceUrl . "</string></value></param>\n";
		$strXml .= "<param><value><string>" . $strTargetUrl . "</string></value></param>\n";
		$strXml .= "</params>\n";
		$strXml .= "</methodCall>\n";

		return $strXml;
	}

	protected function getPingbackUrl($strUrl)
	{
		if(substr_count($strUrl, 'http', 0, 4))
		{
			$found   = false;
			$arrHeaders = $this->request($strUrl, false, true);

			if(isset($arrHeaders) && array_key_exists('X-Pingback', $arrHeaders))
			{
				$strPingbackUrl = $arrHeaders['X-Pingback'];
				$found = true;
			}

			if(!$found)
			{
				$strHtml = $this->request($strUrl, false, false);
				preg_match('/<link rel="pingback" href="([^"]+)"/', $strHtml, $matches);
				$strPingbackUrl = trim($matches[1], "\"\'");
				$found = true;
			}
	
			if($found)
			{
				return $strPingbackUrl;
			}
			else
		    	{
				return false;
			}
		}
	}
}