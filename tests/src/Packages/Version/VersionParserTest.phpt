<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace VenneTests\Packages\Version;

use Tester\Assert;
use Tester\TestCase;
use Venne\Packages\LinkConstraint\EmptyConstraint;
use Venne\Packages\LinkConstraint\MultiConstraint;
use Venne\Packages\LinkConstraint\VersionConstraint;
use Venne\Packages\Version\VersionParser;

require __DIR__ . '/../../bootstrap.php';

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class VersionParserTest extends TestCase
{

	/**
	 * @return mixed
	 */
	public function formattedVersions()
	{
		$data = array(
			array(
				'sourceReference' => 'v2.1.0-RC2',
				'truncate' => true,
				'expected' => 'PrettyVersion v2.1.0-RC2'
			),
			array(
				'sourceReference' => 'bbf527a27356414bfa9bf520f018c5cb7af67c77',
				'truncate' => true,
				'expected' => 'PrettyVersion bbf527a'
			),
			array(
				'sourceReference' => 'v1.0.0',
				'truncate' => false,
				'expected' => 'PrettyVersion v1.0.0'
			),
			array(
				'sourceReference' => 'bbf527a27356414bfa9bf520f018c5cb7af67c77',
				'truncate' => false,
				'expected' => 'PrettyVersion bbf527a27356414bfa9bf520f018c5cb7af67c77'
			),
		);

		$self = $this;
		$createPackage = function ($arr) use ($self) {
			$package = $self->getMock('\Composer\Package\PackageInterface');
			$package->expects($self->once())->method('isDev')->will($self->returnValue(true));
			$package->expects($self->once())->method('getSourceType')->will($self->returnValue('git'));
			$package->expects($self->once())->method('getPrettyVersion')->will($self->returnValue('PrettyVersion'));
			$package->expects($self->any())->method('getSourceReference')->will($self->returnValue($arr['sourceReference']));

			return array($package, $arr['truncate'], $arr['expected']);
		};

		return array_map($createPackage, $data);
	}

	/**
	 * @dataProvider successfulNormalizedVersions
	 */
	public function testNormalizeSucceeds($input, $expected)
	{
		$parser = new VersionParser;
		Assert::same($expected, $parser->normalize($input));
	}

	/**
	 * @return mixed
	 */
	public function successfulNormalizedVersions()
	{
		return array(
			'none' => array('1.0.0', '1.0.0.0'),
			'none/2' => array('1.2.3.4', '1.2.3.4'),
			'parses state' => array('1.0.0RC1dev', '1.0.0.0-RC1-dev'),
			'CI parsing' => array('1.0.0-rC15-dev', '1.0.0.0-RC15-dev'),
			'delimiters' => array('1.0.0.RC.15-dev', '1.0.0.0-RC15-dev'),
			'RC uppercase' => array('1.0.0-rc1', '1.0.0.0-RC1'),
			'patch replace' => array('1.0.0.pl3-dev', '1.0.0.0-patch3-dev'),
			'forces w.x.y.z' => array('1.0-dev', '1.0.0.0-dev'),
			'forces w.x.y.z/2' => array('0', '0.0.0.0'),
			'parses long' => array('10.4.13-beta', '10.4.13.0-beta'),
			'expand shorthand' => array('10.4.13-b', '10.4.13.0-beta'),
			'expand shorthand2' => array('10.4.13-b5', '10.4.13.0-beta5'),
			'strips leading v' => array('v1.0.0', '1.0.0.0'),
			'strips v/datetime' => array('v20100102', '20100102'),
			'parses dates y-m' => array('2010.01', '2010-01'),
			'parses dates w/ .' => array('2010.01.02', '2010-01-02'),
			'parses dates w/ -' => array('2010-01-02', '2010-01-02'),
			'parses numbers' => array('2010-01-02.5', '2010-01-02-5'),
			'parses datetime' => array('20100102-203040', '20100102-203040'),
			'parses dt+number' => array('20100102203040-10', '20100102203040-10'),
			'parses dt+patch' => array('20100102-203040-p1', '20100102-203040-patch1'),
			'parses master' => array('dev-master', '9999999-dev'),
			'parses trunk' => array('dev-trunk', '9999999-dev'),
			'parses branches' => array('1.x-dev', '1.9999999.9999999.9999999-dev'),
			'parses arbitrary' => array('dev-feature-foo', 'dev-feature-foo'),
			'parses arbitrary2' => array('DEV-FOOBAR', 'dev-FOOBAR'),
			'parses arbitrary3' => array('dev-feature/foo', 'dev-feature/foo'),
			'ignores aliases' => array('dev-master as 1.0.0', '9999999-dev'),
		);
	}

	/**
	 * @dataProvider failingNormalizedVersions
	 */
	public function testNormalizeFails($input)
	{
		Assert::exception(function () use ($input) {
			$parser = new VersionParser;
			$parser->normalize($input);
		}, 'UnexpectedValueException');
	}

	/**
	 * @return mixed
	 */
	public function failingNormalizedVersions()
	{
		return array(
			'empty ' => array(''),
			'invalid chars' => array('a'),
			'invalid type' => array('1.0.0-meh'),
			'too many bits' => array('1.0.0.0.0'),
			'non-dev arbitrary' => array('feature-foo'),
		);
	}

	/**
	 * @dataProvider successfulNormalizedBranches
	 */
	public function testNormalizeBranch($input, $expected)
	{
		$parser = new VersionParser;
		Assert::same((string) $expected, (string) $parser->normalizeBranch($input));
	}

	/**
	 * @return mixed
	 */
	public function successfulNormalizedBranches()
	{
		return array(
			'parses x' => array('v1.x', '1.9999999.9999999.9999999-dev'),
			'parses *' => array('v1.*', '1.9999999.9999999.9999999-dev'),
			'parses digits' => array('v1.0', '1.0.9999999.9999999-dev'),
			'parses digits/2' => array('2.0', '2.0.9999999.9999999-dev'),
			'parses long x' => array('v1.0.x', '1.0.9999999.9999999-dev'),
			'parses long *' => array('v1.0.3.*', '1.0.3.9999999-dev'),
			'parses long digits' => array('v2.4.0', '2.4.0.9999999-dev'),
			'parses long digits/2' => array('2.4.4', '2.4.4.9999999-dev'),
			'parses master' => array('master', '9999999-dev'),
			'parses trunk' => array('trunk', '9999999-dev'),
			'parses arbitrary' => array('feature-a', 'dev-feature-a'),
			'parses arbitrary/2' => array('FOOBAR', 'dev-FOOBAR'),
		);
	}

	public function testParseConstraintsIgnoresStabilityFlag()
	{
		$parser = new VersionParser;
		Assert::same((string) new VersionConstraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0@dev'));
	}

	public function testParseConstraintsIgnoresReferenceOnDevVersion()
	{
		$parser = new VersionParser;
		Assert::same((string) new VersionConstraint('=', '1.0.9999999.9999999-dev'), (string) $parser->parseConstraints('1.0.x-dev#abcd123'));
		Assert::same((string) new VersionConstraint('=', '1.0.9999999.9999999-dev'), (string) $parser->parseConstraints('1.0.x-dev#trunk/@123'));
	}

	public function testParseConstraintsFailsOnBadReference()
	{
		Assert::exception(function () {
			$parser = new VersionParser;
			Assert::same((string) new VersionConstraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0#abcd123'));
		}, 'UnexpectedValueException');
		Assert::exception(function () {
			$parser = new VersionParser;
			Assert::same((string) new VersionConstraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0#trunk/@123'));
		}, 'UnexpectedValueException');
	}

	/**
	 * @dataProvider simpleConstraints
	 */
	public function testParseConstraintsSimple($input, $expected)
	{
		$parser = new VersionParser;
		Assert::same((string) $expected, (string) $parser->parseConstraints($input));
	}

	/**
	 * @return mixed
	 */
	public function simpleConstraints()
	{
		return array(
			'match any' => array('*', new EmptyConstraint()),
			'match any/2' => array('*.*', new EmptyConstraint()),
			'match any/3' => array('*.x.*', new EmptyConstraint()),
			'match any/4' => array('x.x.x.*', new EmptyConstraint()),
			'not equal' => array('<>1.0.0', new VersionConstraint('<>', '1.0.0.0')),
			'not equal/2' => array('!=1.0.0', new VersionConstraint('!=', '1.0.0.0')),
			'greater than' => array('>1.0.0', new VersionConstraint('>', '1.0.0.0')),
			'lesser than' => array('<1.2.3.4', new VersionConstraint('<', '1.2.3.4-dev')),
			'less/eq than' => array('<=1.2.3', new VersionConstraint('<=', '1.2.3.0')),
			'great/eq than' => array('>=1.2.3', new VersionConstraint('>=', '1.2.3.0')),
			'equals' => array('=1.2.3', new VersionConstraint('=', '1.2.3.0')),
			'double equals' => array('==1.2.3', new VersionConstraint('=', '1.2.3.0')),
			'no op means eq' => array('1.2.3', new VersionConstraint('=', '1.2.3.0')),
			'completes version' => array('=1.0', new VersionConstraint('=', '1.0.0.0')),
			'shorthand beta' => array('1.2.3b5', new VersionConstraint('=', '1.2.3.0-beta5')),
			'accepts spaces' => array('>= 1.2.3', new VersionConstraint('>=', '1.2.3.0')),
			'accepts master' => array('>=dev-master', new VersionConstraint('>=', '9999999-dev')),
			'accepts master/2' => array('dev-master', new VersionConstraint('=', '9999999-dev')),
			'accepts arbitrary' => array('dev-feature-a', new VersionConstraint('=', 'dev-feature-a')),
			'regression #550' => array('dev-some-fix', new VersionConstraint('=', 'dev-some-fix')),
			'regression #935' => array('dev-CAPS', new VersionConstraint('=', 'dev-CAPS')),
			'ignores aliases' => array('dev-master as 1.0.0', new VersionConstraint('=', '9999999-dev')),
			'lesser than override' => array('<1.2.3.4-stable', new VersionConstraint('<', '1.2.3.4')),
		);
	}

	/**
	 * @dataProvider wildcardConstraints
	 */
	public function testParseConstraintsWildcard($input, $min, $max)
	{
		$parser = new VersionParser;
		if ($min) {
			$expected = new MultiConstraint(array($min, $max));
		} else {
			$expected = $max;
		}

		Assert::same((string) $expected, (string) $parser->parseConstraints($input));
	}

	/**
	 * @return mixed
	 */
	public function wildcardConstraints()
	{
		return array(
			array('2.*', new VersionConstraint('>=', '2.0.0.0-dev'), new VersionConstraint('<', '3.0.0.0-dev')),
			array('20.*', new VersionConstraint('>=', '20.0.0.0-dev'), new VersionConstraint('<', '21.0.0.0-dev')),
			array('2.0.*', new VersionConstraint('>=', '2.0.0.0-dev'), new VersionConstraint('<', '2.1.0.0-dev')),
			array('2.2.x', new VersionConstraint('>=', '2.2.0.0-dev'), new VersionConstraint('<', '2.3.0.0-dev')),
			array('2.10.x', new VersionConstraint('>=', '2.10.0.0-dev'), new VersionConstraint('<', '2.11.0.0-dev')),
			array('2.1.3.*', new VersionConstraint('>=', '2.1.3.0-dev'), new VersionConstraint('<', '2.1.4.0-dev')),
			array('0.*', null, new VersionConstraint('<', '1.0.0.0-dev')),
		);
	}

	/**
	 * @dataProvider tildeConstraints
	 */
	public function testParseTildeWildcard($input, $min, $max)
	{
		$parser = new VersionParser;
		if ($min) {
			$expected = new MultiConstraint(array($min, $max));
		} else {
			$expected = $max;
		}

		Assert::same((string) $expected, (string) $parser->parseConstraints($input));
	}

	/**
	 * @return mixed
	 */
	public function tildeConstraints()
	{
		return array(
			array('~1', new VersionConstraint('>=', '1.0.0.0-dev'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.0', new VersionConstraint('>=', '1.0.0.0-dev'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.0.0', new VersionConstraint('>=', '1.0.0.0-dev'), new VersionConstraint('<', '1.1.0.0-dev')),
			array('~1.2', new VersionConstraint('>=', '1.2.0.0-dev'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.2.3', new VersionConstraint('>=', '1.2.3.0-dev'), new VersionConstraint('<', '1.3.0.0-dev')),
			array('~1.2.3.4', new VersionConstraint('>=', '1.2.3.4-dev'), new VersionConstraint('<', '1.2.4.0-dev')),
			array('~1.2-beta', new VersionConstraint('>=', '1.2.0.0-beta'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.2-b2', new VersionConstraint('>=', '1.2.0.0-beta2'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.2-BETA2', new VersionConstraint('>=', '1.2.0.0-beta2'), new VersionConstraint('<', '2.0.0.0-dev')),
			array('~1.2.2-dev', new VersionConstraint('>=', '1.2.2.0-dev'), new VersionConstraint('<', '1.3.0.0-dev')),
			array('~1.2.2-stable', new VersionConstraint('>=', '1.2.2.0-stable'), new VersionConstraint('<', '1.3.0.0-dev')),
		);
	}

	public function testParseConstraintsMulti()
	{
		$parser = new VersionParser;
		$first = new VersionConstraint('>', '2.0.0.0');
		$second = new VersionConstraint('<=', '3.0.0.0');
		$multi = new MultiConstraint(array($first, $second));
		Assert::same((string) $multi, (string) $parser->parseConstraints('>2.0,<=3.0'));
	}

	public function testParseConstraintsMultiDisjunctiveHasPrioOverConjuctive()
	{
		$parser = new VersionParser;
		$first = new VersionConstraint('>', '2.0.0.0');
		$second = new VersionConstraint('<', '2.0.5.0-dev');
		$third = new VersionConstraint('>', '2.0.6.0');
		$multi1 = new MultiConstraint(array($first, $second));
		$multi2 = new MultiConstraint(array($multi1, $third), false);
		Assert::same((string) $multi2, (string) $parser->parseConstraints('>2.0,<2.0.5 | >2.0.6'));
	}

	public function testParseConstraintsMultiWithStabilities()
	{
		$parser = new VersionParser;
		$first = new VersionConstraint('>', '2.0.0.0');
		$second = new VersionConstraint('<=', '3.0.0.0-dev');
		$multi = new MultiConstraint(array($first, $second));
		Assert::same((string) $multi, (string) $parser->parseConstraints('>2.0@stable,<=3.0@dev'));
	}

	/**
	 * @dataProvider failingConstraints
	 */
	public function testParseConstraintsFails($input)
	{
		Assert::exception(function () use ($input) {
			$parser = new VersionParser;
			$parser->parseConstraints($input);
		}, 'UnexpectedValueException');
	}

	/**
	 * @return mixed
	 */
	public function failingConstraints()
	{
		return array(
			'empty ' => array(''),
			'invalid version' => array('1.0.0-meh'),
		);
	}

	/**
	 * @dataProvider stabilityProvider
	 */
	public function testParseStability($expected, $version)
	{
		Assert::same($expected, VersionParser::parseStability($version));
	}

	/**
	 * @return mixed
	 */
	public function stabilityProvider()
	{
		return array(
			array('stable', '1'),
			array('stable', '1.0'),
			array('stable', '3.2.1'),
			array('stable', 'v3.2.1'),
			array('dev', 'v2.0.x-dev'),
			array('dev', 'v2.0.x-dev#abc123'),
			array('dev', 'v2.0.x-dev#trunk/@123'),
			array('RC', '3.0-RC2'),
			array('dev', 'dev-master'),
			array('dev', '3.1.2-dev'),
			array('stable', '3.1.2-pl2'),
			array('stable', '3.1.2-patch'),
			array('alpha', '3.1.2-alpha5'),
			array('beta', '3.1.2-beta'),
			array('beta', '2.0B1'),
			array('alpha', '1.2.0a1'),
			array('alpha', '1.2_a1'),
			array('RC', '2.0.0rc1')
		);
	}

}

$testCache = new VersionParserTest;
$testCache->run();
