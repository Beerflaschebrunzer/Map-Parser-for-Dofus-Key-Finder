<?php

abstract class SWFTextObjectExporter {

	const IGNORE_AUTOGENERATED = 1;
	const IGNORE_POINT_TEXT = 2;
	
	protected $assets;
	protected $ignoreAutogenerated = true;
	protected $ignorePointText = false;
	protected $document;
	
	public function export($document, $assets) {
		$this->assets = $assets;
		$this->document = $document;
		$this->addFonts();
		$this->addDefaultStyles();
		foreach($assets->textObjects as $textObject) {
			if(!$this->ignoreAutogenerated || !preg_match('/^__id\d+_$/', $textObject->name)) {
				if(!$this->ignorePointText || $textObject->tlfObject->type != 'Point') {
					$this->addSection($textObject);
				}
			}
		}
		$this->assets = null;
		$this->document = null;
	}
	
	public function setPolicy($policy, $value) {
		switch($policy) {
			case self::IGNORE_AUTOGENERATED: $this->ignoreAutogenerated = $value; break;
			case self::IGNORE_POINT_TEXT: $this->ignorePointText = $value; break;
		}
	}
	
	protected function getFontUsage() {
		$hash = array();
		foreach($this->assets->textObjects as $textObject) {
			$textFlow = $textObject->tlfObject->textFlow;
			$textFlowFontFamily = $textFlow->style->fontFamily;
			if(!$textFlowFontFamily) {
				$textFlowFontFamily = 'Arial';	// TLF default
			}
			foreach($textFlow->paragraphs as $paragraph) {
				$paragraphFontFamily = $paragraph->style->fontFamily;
				if(!$paragraphFontFamily) {
					$paragraphFontFamily = $textFlowFontFamily;
				}
				foreach($paragraph->spans as $span) {
					$spanFontFamily = $span->style->fontFamily;
					if(!$spanFontFamily) {
						$spanFontFamily = $paragraphFontFamily;
					}
					$value =& $hash[$spanFontFamily];
					$value += strlen($span->text);
				}
			}
		}
		// sort the array it the most frequently used font is listed first
		arsort($hash);
		return $hash;
	}
	
	protected function getStyleUsage($properties) {
		$table = array();		
		foreach($properties as $name) {
			$table[$name] = array();
		}
			
		// count the number of characters a particular property value is applicable to
		$textLength = 0;
		foreach($this->assets->textObjects as $textObject) {
			$textFlow = $textObject->tlfObject->textFlow;
			$tStyle = $textFlow->style;
			foreach($textFlow->paragraphs as $paragraph) {
				$pStyle = $paragraph->style;
				foreach($paragraph->spans as $span) {
					$sStyle = $span->style;
					$sLength = strlen($span->text);
					foreach($properties as $name) {
						if(($value = $sStyle->$name) !== null || ($value = $pStyle->$name) !== null || ($value = $tStyle->$name) !== null) {
							$row =& $table[$name];
							$count =& $row[$value]; 
							$count += $sLength;
						}
					}
					$textLength += $sLength;
				}
			}
		}
		
		// divide the count by the total length
		foreach($table as $name => &$row) {
			// more frequently used item comes first
			arsort($row);
			foreach($row as &$value) {
				$value = (double) $value / $textLength;
			}
		}
		return $table;
	}	
			
	protected function beautifySectionName($name) {
		if(strpos($name, '_') === false) {	// don't change the name if underscores are used
			$name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);	// split up a camel-case name
			$name = preg_replace('/([a-zA-Z])(\d+)$/', '$1 $2', $name);	// put a space in front of trailing number
			$name = ucfirst($name);						// capitalize first letter		
		}
		return $name;
	}
}

?>