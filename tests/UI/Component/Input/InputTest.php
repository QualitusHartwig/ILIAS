<?php

/* Copyright (c) 2016 Richard Klees <richard.klees@concepts-and-training.de> Extended GPL, see docs/LICENSE */

require_once(__DIR__."/../../../../libs/composer/vendor/autoload.php");
require_once(__DIR__."/../../Base.php");

use \ILIAS\UI\Implementation\Component\Input\Input;
use \ILIAS\Data\Factory as DataFactory;
use \ILIAS\Transformation\Factory as TransformationFactory;
use \ILIAS\Validation\Factory as ValidationFactory;
use \ILIAS\Data\Result;

class DefInput extends Input {
	public $value_ok = true;
	protected function isClientSideValueOk($value) {
		return $this->value_ok;
	}
}

/**
 * Test on input implementation.
 */
class InputTest extends ILIAS_UI_TestBase {
	public function setUp() {
		$this->data_factory = new DataFactory();
		$this->transformation_factory = new TransformationFactory();
		$this->validation_factory = new ValidationFactory($this->data_factory);
		$this->input = new DefInput($this->data_factory, "label", "byline");
	}

	public function test_constructor() {
		$this->assertEquals("label", $this->input->getLabel());
		$this->assertEquals("byline", $this->input->getByline());
	}

	public function test_withLabel() {
		$label = "new label";
		$input = $this->input->withLabel($label);
		$this->assertEquals($label, $input->getLabel());
		$this->assertNotSame($this->input, $input);
	}

	public function test_withByline() {
		$byline = "new byline";
		$input = $this->input->withByline($byline);
		$this->assertEquals($byline, $input->getByline());
		$this->assertNotSame($this->input, $input);
	}

	public function test_withValue() {
		$value = "some value";
		$input = $this->input->withValue($value);
		$this->assertEquals(null, $this->input->getValue());
		$this->assertEquals($value, $input->getValue());
		$this->assertNotSame($this->input, $input);
	}

	public function test_withValue_throws() {
		$this->input->value_ok = false;
		$raised = false;
		try {
			$this->input->withValue("foo");
			$this->assertFalse("This should not happen.");
		}
		catch (\InvalidArgumentException $e) {
			$raised = true;
		}
		$this->assertTrue($raised);
		$this->assertEquals(null, $this->input->getValue());
	}

	public function test_withName() {
		$name = "name";
		$input = $this->input->withName($name);
		$this->assertEquals(null, $this->input->getName());
		$this->assertEquals($name, $input->getName());
		$this->assertNotSame($this->input, $input);
	}

	public function test_withError() {
		$error = "error";
		$input = $this->input->withError($error);
		$this->assertEquals(null, $this->input->getError());
		$this->assertEquals($error, $input->getError());
		$this->assertNotSame($this->input, $input);
	}

	public function test_getContent() {
		$this->assertEquals(null, $this->input->getContent());
	}	

	public function test_withInput() {
		$name = "name";
		$value = "value";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($value, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
	}

	public function test_only_run_withInput_with_name() {
		$raised = false;
		try {
			$this->input->withInput([]);
			$this->assertFalse("This should not happen.");
		}
		catch (\LogicException $e) {
			$raised = true;
		}
		$this->assertTrue($raised);
	}

	public function test_withInput_and_transformation() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertEquals($value, $v);
				return $transform_to;
			}))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($transform_to, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
	}

	public function test_withInput_and_transformation_different_order() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withInput($values)
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertEquals($value, $v);
				return $transform_to;
			}));
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($transform_to, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
	}

	public function test_withInput_and_constraint_successfull() {
		$name = "name";
		$value = "value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withConstraint($this->validation_factory->custom(function($_) { return true; }, $error))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($value, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals(null, $input2->getError());
	}

	public function test_withInput_and_constraint_fails() {
		$name = "name";
		$value = "value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withConstraint($this->validation_factory->custom(function($_) { return false; }, $error))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isError());
		$this->assertEquals($error, $res->error());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals($error, $input2->getError());
	}

	public function test_withInput_and_constraint_fails_different_order() {
		$name = "name";
		$value = "value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withInput($values)
			->withConstraint($this->validation_factory->custom(function($_) { return false; }, $error));
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isError());
		$this->assertEquals($error, $res->error());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals($error, $input2->getError());
	}

	public function test_withInput_transformation_and_constraint() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertEquals($value, $v);
				return $transform_to;
			}))
			->withConstraint($this->validation_factory->custom(function($v) use ($transform_to) {
				$this->assertEquals($transform_to, $v);
				return true;
			}, $error))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($transform_to, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals(null, $input2->getError());
	}

	public function test_withInput_transformation_and_constraint_different_order() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withInput($values)
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertEquals($value, $v);
				return $transform_to;
			}))
			->withConstraint($this->validation_factory->custom(function($v) use ($transform_to) {
				$this->assertEquals($transform_to, $v);
				return true;
			}, $error));
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($transform_to, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals(null, $input2->getError());
	}

	public function test_withInput_constraint_and_transformation() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withConstraint($this->validation_factory->custom(function($v) use ($value) {
				$this->assertEquals($value, $v);
				return true;
			}, $error))
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertEquals($value, $v);
				return $transform_to;
			}))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isOk());
		$this->assertEquals($transform_to, $res->value());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals(null, $input2->getError());
	}

	public function test_withInput_constraint_fails_and_transformation() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withConstraint($this->validation_factory->custom(function($v) use ($value) {
				$this->assertEquals($value, $v);
				return false;
			}, $error))
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertFalse("This should not happen");
				return $transform_to;
			}))
			->withInput($values);
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isError());
		$this->assertEquals($error, $res->error());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals($error, $input2->getError());
	}

	public function test_withInput_constraint_fails_and_transformation_different_order() {
		$name = "name";
		$value = "value";
		$transform_to = "other value";
		$error = "an error";
		$input = $this->input->withName($name);
		$values = [$name => $value];

		$input2 = $input
			->withInput($values)
			->withConstraint($this->validation_factory->custom(function($v) use ($value) {
				$this->assertEquals($value, $v);
				return false;
			}, $error))
			->withTransformation($this->transformation_factory->custom(function($v) use ($value, $transform_to) {
				$this->assertFalse("This should not happen");
				return $transform_to;
			}));
		$res = $input2->getContent();

		$this->assertInstanceOf(Result::class, $res);
		$this->assertTrue($res->isError());
		$this->assertEquals($error, $res->error());

		$this->assertNotSame($input, $input2);
		$this->assertEquals($value, $input2->getValue());
		$this->assertEquals($error, $input2->getError());
	}
}
