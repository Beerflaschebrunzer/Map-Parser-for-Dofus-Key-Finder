<?php

class ASSourceCodeDumper {

	protected $frameIndex;
	protected $symbolCount;
	protected $symbolName;
	protected $symbolNames;
	protected $instanceCount;
	protected $decoderAVM1;
	protected $decompilerAS2;
	protected $decoderAVM2;
	protected $decompilerAS3;
				
	public function getRequiredTags() {
		return array('DoABC', 'DoAction', 'DoInitAction', 'PlaceObject2', 'PlaceObject3', 'DefineSprite', 'DefineBinaryData', 'ShowFrame');
	}
	
	public function dump($swfFile) {
		$this->frameIndex = 1;
		$this->symbolName = "_root";
		$this->symbolCount = 0;
		$this->symbolNames = array( 0 => $this->symbolName);
		$this->instanceCount = 0;
		foreach($swfFile->tags as &$tag) {
			$this->processTag($tag);
			$tag = null;
		}
	}

	protected function processTag($tag) {
		if($tag instanceof SWFDoABCTag) {
			if(!$this->decoderAVM2) {
				$this->decoderAVM2 = new AVM2Decoder;
			}
			if(!$this->decompilerAS3) {
				$this->decompilerAS3 = new AS3Decompiler;
			}
			$scripts = $this->decoderAVM2->decode($tag->abcFile);
			$tag->abcFile = null;
			foreach($scripts as &$script) {
				$package = $this->decompilerAS3->decompile($script);
				$script = null;
				$this->printStatement($package);
				$packages = null;
			}
		} else if($tag instanceof SWFDoActionTag || $tag instanceof SWFDoInitActionTag) {
			// an empty tag would still contain the zero terminator
			if(strlen($tag->actions) > 1) {
				if($tag instanceof SWFDoInitActionTag) {
					$symbolName = $this->symbolNames[$tag->characterId];
					echo "<div class='comments'>// $symbolName initialization </div>";
				} else {
					echo "<div class='comments'>// $this->symbolName, frame $this->frameIndex </div>";
				}
				if(!$this->decoderAVM1) {
					$this->decoderAVM1 = new AVM1Decoder;
				}
				if(!$this->decompilerAS2) {
					$this->decompilerAS2 = new AS2Decompiler;
				}
				$operations = $this->decoderAVM1->decode($tag->actions);
				$tag->actions = null;
				$statements = $this->decompilerAS2->decompile($operations);
				$operations = null;
				$this->printStatements($statements);
				$statements = null;
			}
		} else if($tag instanceof SWFPlaceObject2Tag) {
			$this->instanceCount++;
			if($tag->clipActions) {
				static $eventNames = array(	0x00040000 => "construct",		0x00020000 => "keyPress", 
											0x00010000 => "dragOut", 		0x00008000 => "dragOver",
											0x00004000 => "rollOut", 		0x00002000 => "rollOver",
											0x00001000 => "releaseOutside",	0x00000800 => "release",
											0x00000400 => "press",			0x00000200 => "initialize",
											0x00000100 => "data",			0x00000080 => "keyUp",
											0x00000040 => "keyDown",		0x00000020 => "mouseUp",
											0x00000010 => "mouseDown",		0x00000008 => "mouseMove",
											0x00000004 => "inload",			0x00000002 => "enterFrame",
											0x00000001 => "load"	);
				
				$instanceName = ($tag->name) ? $tag->name : "instance$this->instanceCount";
				$instancePath = "$this->symbolName.$instanceName";
				echo "<div class='comments'>// $instancePath</div>";
				foreach($tag->clipActions as $clipAction) {
					echo "<div>on(";
					$eventCount = 0;
					foreach($eventNames as $flag => $eventName) {
						if($clipAction->eventFlags & $flag) {
							if($eventCount > 0) {
								echo ", ";
							}
							echo "<span class='name'>$eventName</span>";
							$eventCount++;
						}
					}
					echo ") {\n";
					if(!$this->decoderAVM1) {
						$this->decoderAVM1 = new AVM1Decoder;
					}
					if(!$this->decompilerAS2) {
						$this->decompilerAS2 = new AS2Decompiler;
					}
					$operations = $this->decoderAVM1->decode($clipAction->actions);
					$clipAction->actions = null;
					$statements = $this->decompilerAS2->decompile($operations);
					$operations = null;
					echo "<div class='code-block'>\n";
					$this->printStatements($statements);
					echo "</div>}\n";
					$statements = null;
				}
			}
		} else if($tag instanceof SWFShowFrameTag) {
			$this->frameIndex++;
		} else if($tag instanceof SWFDefineSpriteTag) {
			$prevSymbolName = $this->symbolName;
			$prevFrameIndex = $this->frameIndex;
			$prevInstanceCount = $this->instanceCount;
			$this->symbolName = $this->symbolNames[$tag->characterId] = "symbol" . ++$this->symbolCount;
			$this->frameIndex = 1;
			$this->instanceCount = 0;
			foreach($tag->tags as $tag) {
				$this->processTag($tag);
			}
			$this->symbolName = $prevSymbolName;
			$this->frameIndex = $prevFrameIndex;
			$this->instanceCount = $prevInstanceCount;
		} else if($tag instanceof SWFDefineBinaryDataTag) {
			if($tag->swfFile) {
				$dumper = clone $this;
				$dumper->dump($tag->swfFile);
			}
		}
	}
	
	protected function printExpression($expr, $precedence = null) {
	    var_dump($expr);
		$type = gettype($expr);
		switch($type) {
			case 'boolean': 
				$text = ($expr) ? 'true' : 'false';
				echo "<span class='boolean'>$text</span>"; 
				break;
			case 'double':
				$text = is_nan($expr) ? 'NaN' : (string) $expr;
				echo "<span class='double'>$text</span>"; 
				break;
			case 'integer': 
				$text = (string) $expr;
				echo "<span class='integer'>$text</span>"; 
				break;
			case 'string':
				$text = '"' . htmlspecialchars(addcslashes($expr, "\\\"\n\r\t")) . '"';
				echo "<span class='string'>$text</span>";
				break;
			case 'NULL':
				echo "<span class='null'>null</span>";
				break;
			case 'object':
				if($expr instanceof AS2Identifier || $expr instanceof AS3Identifier) {
					$text = htmlspecialchars($expr->string);
					echo "<span class='name'>$text</span>";
				} else if($expr instanceof AVM1Undefined || $expr instanceof AVM2Undefined) {
					echo "<span class='undefined'>undefined</span>";
				} else if($expr instanceof AVM1Register || $expr instanceof AVM2Register) {
					if($expr->name) {
						$text = $expr->name;
					} else {
						$text = "REG_$expr->index";
					}
					echo "<span class='register'>$text</span>";
				} else if($expr instanceof AS3TypeCoercion) {
					$this->printExpression($expr->value, $precedence);
				} else if($expr instanceof AS3Argument) {
					$this->printExpression($expr->name);
					echo ":";
					$this->printExpression($expr->type);
					if($expr->defaultValue !== null) {
						echo " = ";
						$this->printExpression($expr->defaultValue);
					}
				} else if($expr instanceof AS3Accessor)	{
					echo "<span class='keyword'>$expr->type</span> ";
					$this->printExpression($expr->name);
				} else if($expr instanceof AS2Function || $expr instanceof AS3Function) {
					echo "<span class='keyword'>function</span>";
					if(isset($expr->name)) {
						$this->printExpression($expr->name);
					}
					echo "(";
					$this->printExpressions($expr->arguments);
					echo ")";
					if(isset($expr->returnType)) {
						echo ":";
						$this->printExpression($expr->returnType);
					}
					echo " {<div class='code-block'>\n";
					$this->printStatements($expr->statements);
					echo "</div>}";
				} else if($expr instanceof AS2FunctionCall || $expr instanceof AS3FunctionCall) {
					$this->printExpression($expr->name);
					echo "(";
					$this->printExpressions($expr->arguments);
					echo ")";
				} else if($expr instanceof AS2VariableDeclaration || $expr instanceof AS3VariableDeclaration) {
					echo "<span class='keyword'>var</span> ";
					$this->printExpression($expr->name);
					if(isset($expr->type)) {
						echo ":";
						$this->printExpression($expr->type);
					}
					if(!($expr->value instanceof AVM1Undefined) && !($expr->value instanceof AVM2Undefined)) {
						echo " = ";
						$this->printExpression($expr->value);
					}
				} else if($expr instanceof AS2ArrayInitializer || $expr instanceof AS3ArrayInitializer) {
					if($expr->items) {
						echo "[ ";
						$this->printExpressions($expr->items);
						echo " ]";
					} else {
						echo "[]";
					}
				} else if($expr instanceof AS2ObjectInitializer || $expr instanceof AS3ObjectInitializer) {
					if($expr->items) {
						echo "{ ";
						$count = 0;
						foreach($expr->items as $index => $value) {
							if($index & 1) {
								echo ": ";
							} else {
								if($count++ > 0) {
									echo ", ";
								}
							}
							$this->printExpression($value);
						}
						echo " }";
					} else {
						echo "{}";
					}
				} else if($expr instanceof AS2Operation || $expr instanceof AS3Operation) {
					static $noSpace = array('.' => true, '..' => true, '!' => true, '~' => true, '++' => true, '--' => true);
					if($precedence !== null) {
						if($expr instanceof AS2Operation && $precedence < $expr->precedence) {
							$needParentheses = true;
						} else if($expr instanceof AS3Operation && $precedence < $expr->precedence) {
							$needParentheses = true;
						} else {
							$needParentheses = false;
						}
					} else {
						$needParentheses = false;
					}
					if($needParentheses) {
						echo "(";
					}
					if($expr instanceof AS2BinaryOperation || $expr instanceof AS3BinaryOperation) {
						$this->printExpression($expr->operand1, $expr->precedence);
						echo isset($noSpace[$expr->operator]) ? $expr->operator : " $expr->operator ";
						$this->printExpression($expr->operand2, $expr->precedence);
					} else if($expr instanceof AS2UnaryOperation || $expr instanceof AS3UnaryOperation) {
						echo isset($noSpace[$expr->operator]) ? $expr->operator : " $expr->operator ";
						$this->printExpression($expr->operand, $expr->precedence);
					} else if($expr instanceof AS2TernaryConditional || $expr instanceof AS3TernaryConditional) {
						$this->printExpression($expr->condition, $expr->precedence);
						echo " ? ";
						$this->printExpression($expr->valueIfTrue, $expr->precedence);
						echo " : ";
						$this->printExpression($expr->valueIfFalse, $expr->precedence);
					} else if($expr instanceof AS2ArrayAccess || $expr instanceof AS3ArrayAccess) {
						$this->printExpression($expr->array);
						echo "[";
						$this->printExpression($expr->index);
						echo "]";
					}
					if($needParentheses) {
						echo ")";
					}
				}
				break;
		}
	}
	
	protected function printExpressions($expressions) {
		foreach($expressions as $index => $expr) {
			if($index > 0) {
				echo ", ";
			}
			$this->printExpression($expr);
		}
	}
	
	protected function printStatement($stmt) {
		if($stmt instanceof AS2SimpleStatement || $stmt instanceof AS3SimpleStatement) {
			if($stmt instanceof AS2BasicStatement || $stmt instanceof AS3BasicStatement) {
				$this->printExpression($stmt->expression);
			} else if($stmt instanceof AS2Break || $stmt instanceof AS3Break) {
				echo "<span class='keyword'>break</span>";
				if(isset($stmt->label)) {
					echo " <span class='label'>$stmt->label</span>";
				}
			} else if($stmt instanceof AS2Continue || $stmt instanceof AS3Continue) {
				echo "<span class='keyword'>continue</span>";
				if(isset($stmt->label)) {
					echo " <span class='label'>$stmt->label</span>";
				}
			} else if($stmt instanceof AS2Return || $stmt instanceof AS3Return) {
				echo "<span class='keyword'>return</span>";
				if(!($stmt->value instanceof AVM1Undefined) && !($stmt->value instanceof AVM2Undefined)) {
					echo " ";
					$this->printExpression($stmt->value);
				}
			} else if($stmt instanceof AS2Throw || $stmt instanceof AS3Throw) {
				echo "<span class='keyword'>throw</span>(";
				$this->printExpression($stmt->object);
				echo ")";
			} else if($stmt instanceof AS2ClassVariable || $stmt instanceof AS3ClassVariable) {
				foreach($stmt->modifiers as $modifier) {
					echo "<span class='keyword'>$modifier</span> ";
				}
				echo "<span class='keyword'>var</span> ";
				$this->printExpression($stmt->name);
				if(isset($stmt->type)) {
					echo ":";
					$this->printExpression($stmt->type);
				}
				if(!($stmt->value instanceof AVM1Undefined) && !($stmt->value instanceof AVM2Undefined)) {
					echo " = ";
					$this->printExpression($stmt->value);
				}
			} else if($stmt instanceof AS3ClassConstant) {
				foreach($stmt->modifiers as $modifier) {
					echo "<span class='keyword'>$modifier</span> ";
				}
				echo "<span class='keyword'>const</span> ";
				$this->printExpression($stmt->name);
				if(isset($stmt->type)) {
					echo ":";
					$this->printExpression($stmt->type);
				}
				if(!($stmt->value instanceof AVM1Undefined) && !($stmt->value instanceof AVM2Undefined)) {
					echo " = ";
					$this->printExpression($stmt->value);
				}
			}
			echo ";";
		} else if($stmt instanceof AS2CompoundStatement || $stmt instanceof AS3CompoundStatement) {
			if($stmt instanceof AS2IfElse || $stmt instanceof AS3IfElse) {
				echo "<span class='keyword'>if</span>(";
				$this->printExpression($stmt->condition);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statementsIfTrue);
				echo "</div>}\n";
				if($stmt->statementsIfFalse) {
					if(count($stmt->statementsIfFalse) == 1 && ($stmt->statementsIfFalse[0] instanceof AS2IfElse || $stmt->statementsIfFalse[0] instanceof AS3IfElse)) {
						// else if
						echo "<span class='keyword'>else</span> ";
						$this->printStatement($stmt->statementsIfFalse[0]);
					} else {
						echo "<span class='keyword'>else</span> {\n<div class='code-block'>\n";
						$this->printStatements($stmt->statementsIfFalse);
						echo "</div>}\n";
					}
				}
			} else if($stmt instanceof AS2While || $stmt instanceof AS3While) {
				if(isset($stmt->label)) {
					echo "<span class='label'>$stmt->label</span>: ";
				}
				echo "<span class='keyword'>while</span>(";
				$this->printExpression($stmt->condition);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>}\n";
			} else if($stmt instanceof AS2ForIn || $stmt instanceof AS3ForIn) {
				echo "<span class='keyword'>for</span>(";
				$this->printExpression($stmt->condition);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>}\n";
			} else if($stmt instanceof AS3Foreach) {
				echo "<span class='keyword'>for each</span>(";
				$this->printExpression($stmt->condition);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>}\n";
			} else if($stmt instanceof AS2DoWhile || $stmt instanceof AS3DoWhile) {
				echo "<span class='keyword'>do {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>} while(";
				$this->printExpression($stmt->condition);
				echo ");\n";
			} else if($stmt instanceof AS2Switch || $stmt instanceof AS3Switch) {
				echo "<span class='keyword'>switch</span>(";
				$this->printExpression($stmt->compareValue);
				echo ") {\n<div class='code-block'>\n";
				foreach($stmt->cases as $case) {
					echo "<span class='keyword'>case</span> ";
					$this->printExpression($case->constant);
					echo ": ";
					echo "<div class='code-block'>\n";
						$this->printStatements($case->statements);
					echo "</div>\n";
				}
				if($stmt->defaultCase) {
					echo "<span class='keyword'>default:</span>";
					echo "<div class='code-block'>\n";
					$this->printStatements($stmt->defaultCase->statements);
					echo "</div>\n";
				}
				echo "</div>}\n";
			} else if($stmt instanceof AS2TryCatch || $stmt instanceof AS3TryCatch) {
				echo "<span class='keyword'>try</span> {\n<div class='code-block'>\n";
				$this->printStatements($stmt->tryStatements);
				echo "</div>}\n";
				if($statements = $stmt->catchStatements) {
					echo "<span class='keyword'>catch</span>(";
					$this->printExpression($stmt->catchObject);
					echo ") {\n<div class='code-block'>\n";
					$this->printStatements($statements);
					echo "</div>}\n";
				}
				if($statements = $stmt->finallyStatements) {
					echo "<span class='keyword'>finally</span> {\n<div class='code-block'>\n";
					$this->printStatements($statements);
					echo "</div>}\n";
				}
			} else if($stmt instanceof AS2With) {
				echo "<span class='keyword'>with</span>(";
				$this->printExpression($stmt->object);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>}\n";
			} else if($stmt instanceof AS2IfFrameLoaded) {
				echo "<span class='keyword'>ifFrameLoaded</span>(";
				$this->printExpression($stmt->frame);
				echo ") {\n<div class='code-block'>\n";
				$this->printStatements($stmt->statements);
				echo "</div>}\n";
			} else if($stmt instanceof AS3Package) {
				echo "<div class='package'>";
				echo "<span class='keyword'>package</span> ";
				if($stmt->namespace) {
					$this->printExpression($stmt->namespace);
				}
				echo " {\n<div class='code-block'>\n";
				foreach($stmt->imports as $import) {
					echo "<div><span class='keyword'>import</span> ";
					$this->printExpression($import);
					echo ";</div>";
				}
				foreach($stmt->members as $member) {
					if(in_array('public', $member->modifiers)) {
						echo "<div>\n";
						$this->printStatement($member);
						echo "</div>\n";
					}
				}
				echo "</div>}\n";
				foreach($stmt->members as $member) {
					if(!in_array('public', $member->modifiers)) {
						echo "<div>\n";
						$this->printStatement($member);
						echo "</div>\n";
					}
				}
				echo "</div>";
			} else if($stmt instanceof AS2Class || $stmt instanceof AS3Class) {
				foreach($stmt->modifiers as $modifier) {
					echo "<span class='keyword'>$modifier</span> ";
				}
				if($stmt instanceof AS2Interface || $stmt instanceof AS3Interface) {
					echo "<span class='keyword'>interface</span> ";
				} else {
					echo "<span class='keyword'>class</span> ";
				}
				$this->printExpression($stmt->name);
				if($stmt->parentName) {
					echo " <span class='keyword'>extends</span> ";
					$this->printExpression($stmt->parentName);
				}
				if($stmt->interfaces) {
					echo " <span class='keyword'>implements</span> ";
					foreach($stmt->interfaces as $index => $interface) {
						if($index != 0) {
							echo ", ";
						}
						$this->printExpression($interface);
					}
				}
				echo " {\n<div class='code-block'>\n";
				$this->printStatements($stmt->members);
				echo "</div>}\n";
			} else if($stmt instanceof AS2ClassMethod || $stmt instanceof AS3ClassMethod || $stmt instanceof AS3Function) {
				foreach($stmt->modifiers as $modifier) {
					echo "<span class='keyword'>$modifier</span> ";
				}
				echo "<span class='keyword'>function</span> ";
				if($stmt->name) {
					$this->printExpression($stmt->name);
				}
				echo "(";
				$this->printExpressions($stmt->arguments);
				echo ")";
				if(isset($stmt->returnType)) {
					echo ":";
					$this->printExpression($stmt->returnType);
				}
				if($statements = $stmt->statements) {
					echo " {\n<div class='code-block'>\n";
					$this->printStatements($statements);
					echo "</div>}\n";
				} else {
					echo ";";
				}
			} else if($stmt instanceof AS3StaticInitializer) {
				if($statements = $stmt->statements) {
					echo " {\n<div class='code-block'>\n";
					$this->printStatements($statements);
					echo "</div>}\n";
				}
			}
		}
	}
	
	protected function printStatements($statements) {
		foreach($statements as $stmt) {
			echo "<div>\n";
			$this->printStatement($stmt);
			echo "</div>\n";
		}
	}
}

?>