<?php

class FloogleDocs extends SWFGeneratorDataModule {

	protected $suppliedUrl;
	protected $sourceUrl;
	protected $input;
	protected $updater;

	public function __construct($moduleConfig, &$persistentData) {
		parent::__construct($moduleConfig, $persistentData);
		// https://docs.google.com/document/d/[document id]/edit
		$this->suppliedUrl = isset($moduleConfig['url']) ? trim($moduleConfig['url']) : null;
		// https://docs.google.com/document/d/[document id]/export?format=odt
		$this->sourceUrl = preg_match('/(.*?)edit$/', $this->suppliedUrl, $m) ? "{$m[1]}export?format=odt" : $this->suppliedUrl;
	}

	public function startTransfer() {
		$this->input = fopen($this->sourceUrl, "rb");
		return ($this->input) ? true : false;
	}
	
	public function finishTransfer() {
		if($this->input) {
			// parse the ODT file
			$parser = new ODTParser;
			$document = $parser->parse($this->input);
			fclose($this->input);
			$this->updater = new SWFTextObjectUpdaterODT($document);
			return true;
		}
	}

	public function update($assets) {
		if($this->updater) {
			// update the text objects
			$this->updater->setPolicy(SWFTextObjectUpdater::ALLOWED_DEVICE_FONTS, $this->allowedDeviceFonts);
			$this->updater->setPolicy(SWFTextObjectUpdater::MAINTAIN_ORIGINAL_FONT_SIZE, $this->maintainOriginalFontSize);
			$this->updater->setPolicy(SWFTextObjectUpdater::ALLOW_ANY_EMBEDDED_FONT, $this->allowAnyEmbeddedFont);
			return $this->updater->update($assets);
		}
		return array();
	}
	
	public function cleanUp() {
		unset($this->updater);
	}
	
	public function validate() {
		if($this->suppliedUrl) {
			echo "<div class='subsection-ok'><b>Supplied URL:</b> {$this->suppliedUrl}</div>";
			echo "<div class='subsection-ok'><b>Retrieval URL:</b> {$this->sourceUrl}</div>";
			flush();
			$startTime = microtime(true);
			$this->startTransfer();
			if($this->input) {
				$parser = new ODTParser;
				$document = $parser->parse($this->input);
				fclose($this->input);				
				if($document) {
					$updater = new SWFTextObjectUpdaterODT($document);
					$sections = $updater->getSectionNames();
					$sectionCount = count($sections);
					if($sectionCount) {
						$descriptions = array();
						foreach($sections as $section) {
							$descriptions[] = "\"$section\"";
						}
						$descriptions = implode(', ', $descriptions);
						echo "<div class='subsection-ok'><b>Text sections ($sectionCount): </b> $descriptions</div>";
					} else {
						echo "<div class='subsection-err'><b>Text sections ($sectionCount): </b></div>";
					}
					$endTime = microtime(true);
					$duration = sprintf("%0.4f", $endTime - $startTime);
					echo "<div class='subsection-ok'><b>Process time: </b> $duration second(s)</div>";
				} else {
					echo "<div class='subsection-err' style='text-align: center'><em>(errors encountered reading document)</em></div>";
				}				
			} else {
				if(!in_array('openssl', get_loaded_extensions())) {
					echo "<div class='subsection-err' style='text-align: center'><em>(cannot download document without OpenSSL)</em></div>";
				} else {
					echo "<div class='subsection-err' style='text-align: center'><em>(cannot download document)</em></div>";
				}
			}
		} else {
			echo "<div class='subsection-err'><b>Supplied URL:</b> <em>(none)</em></div>";
		}
	}
	
	public function getExportType() {
		return 'application/vnd.oasis.opendocument.text';
	}
	
	public function getExportFileName() {
		return 'FloogleDocs.odt';
	}
	
	public function export(&$output, $assets) {
		// export the text into an ODTDocument object
		$document = new ODTDocument;
		$exporter = new SWFTextObjectExporterODT;
		$exporter->setPolicy(SWFTextObjectExporter::IGNORE_AUTOGENERATED, $this->ignoreAutogenerated);
		$exporter->setPolicy(SWFTextObjectExporter::IGNORE_POINT_TEXT, $this->ignorePointText);
		$exporter->export($document, $assets);

		// assemble it into a ODT file
		$assembler = new ODTAssembler;
		$assembler->assemble($output, $document);
	}
	
	public function getRequiredPHPExtensions() {
		return array('OpenSSL', 'PCRE', 'XML', 'Zlib');
	}
	
	public function getModuleSpecificParameters() {
		return array('googledocs' => "Redirect to document at GoogleDocs");
	}
	
	public function runModuleSpecificOperation($parameters) {
		if(isset($parameters['googledocs'])) {
			$format = $parameters['googledocs'];
			if($format) {
				$url = preg_match('/(.*?)edit$/', $this->suppliedUrl, $m) ? "{$m[1]}export?format={$format}" : $this->suppliedUrl;
			} else {
				$url = $this->suppliedUrl;
			}
			$this->redirect($url);
			return true;
		}
		return false;
	}
	
	protected function redirect($url) {
		header("Location: $url");
	}	
}

?>