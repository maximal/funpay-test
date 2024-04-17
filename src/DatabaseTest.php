<?php

namespace FpDbTest;

use Exception;

class DatabaseTest
{
	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	public function testBuildQuery(): void
	{
		$results = [];

		$results[] = $this->db->buildQuery('select name from users where user_id = 1');

		$results[] = $this->db->buildQuery(
			'select * from users where name = ? and block = 0',
			['Jack']
		);

		$results[] = $this->db->buildQuery(
			'select ?# from users where user_id = ?d and block = ?d',
			[['name', 'email'], 2, true]
		);

		$results[] = $this->db->buildQuery(
			'update users set ?a where user_id = -1',
			[['name' => 'Jack', 'email' => null]]
		);

		foreach ([null, true] as $block) {
			$results[] = $this->db->buildQuery(
				'select name from users where ?# in (?a){ and block = ?d}',
				['user_id', [1, 2, 3], $block ?? $this->db->skip()]
			);
		}

		$correct = [
			'select name from users where user_id = 1',
			'select * from users where name = \'Jack\' and block = 0',
			'select `name`, `email` from users where user_id = 2 and block = 1',
			'update users set `name` = \'Jack\', `email` = null where user_id = -1',
			'select name from users where `user_id` in (1, 2, 3)',
			'select name from users where `user_id` in (1, 2, 3) and block = 1',
		];

		if ($results !== $correct) {
			echo 'Expected: ';
			var_dump($correct);
			echo 'Actual: ';
			var_dump($results);
			throw new Exception('Failure.');
		}


		/**
		 * Далее — дополнительно написанные тесты
		 *
		 * @since 2024-04-17
		 * @author MaximAL
		 */

		// Дополнительные тесты
		echo 'Running additional tests...', PHP_EOL;

		$additionals = [];
		$additionalsExpected = [];

		// Тест с `?` внутри строкового литерала и плейсхолдером в конце запроса
		$additionals[] = $this->db->buildQuery(
			'select * from users where name = \'J?ack\' and block = ?',
			[true]
		);
		$additionalsExpected[]= 'select * from users where name = \'J?ack\' and block = 1';

		// Тест с `{...}` внутри строкового литерала и плейсхолдером в конце запроса
		$additionals[] = $this->db->buildQuery(
			'select * from users where name = \'J ?a {insi ?d e} ck\' and block = ?',
			[true]
		);
		$additionalsExpected[]= 'select * from users where name = \'J ?a {insi ?d e} ck\' and block = 1';

		// Тест с условным блоком: открытие сразу после `?`
		$additionals[] = $this->db->buildQuery(
			'update users set ?a where user_id = ?{ and (block = ? or email = ?)}',
			[['name' => 'Jack', 'email' => null], 2, null, 'jack@me.com']
		);
		$additionalsExpected[]= 'update users set `name` = \'Jack\', `email` = null where user_id = 2';

		$additionals[] = $this->db->buildQuery(
			'update users set ?a where user_id = ?{ and (block = ? or email = ?)}',
			[['name' => 'Jack', 'email' => null], 2, true, 'jack@me.com']
		);
		$additionalsExpected[]= 'update users set `name` = \'Jack\', `email` = null where user_id = 2 and (block = 1 or email = \'jack@me.com\')';

		// Тест с условным блоком: открытие и закрытие сразу после `?`
		$additionals[] = $this->db->buildQuery(
			'update users set ?a where user_id = ?{ or email = ?}',
			[['name' => 'Jack', 'email' => null], 2, null]
		);
		$additionalsExpected[]= 'update users set `name` = \'Jack\', `email` = null where user_id = 2';

		$additionals[] = $this->db->buildQuery(
			'update users set ?a where user_id = ?{ or email = ?}',
			[['name' => 'Jack', 'email' => null], 2, 'jack@me.com']
		);
		$additionalsExpected[]= 'update users set `name` = \'Jack\', `email` = null where user_id = 2 or email = \'jack@me.com\'';


		if ($additionals !== $additionalsExpected) {
			echo 'Expected: ';
			var_dump($additionalsExpected);
			echo 'Actual: ';
			var_dump($additionals);
			throw new Exception('Additional tests failure.');
		}


		// Тесты на ошибки парсера
		echo 'Running parse error tests...', PHP_EOL;

		$errorFound = false;
		try {
			$this->db->buildQuery('select * from users where name = \'J?ack');
		} catch (ParseError) {
			$errorFound = true;
		}
		if (!$errorFound) {
			throw new Exception('Parse error tests failure.');
		}

		$errorFound = false;
		try {
			$this->db->buildQuery(
				'select * from users where name = \'J?ack\' and {block = ?',
				[true]
			);
		} catch (ParseError) {
			$errorFound = true;
		}
		if (!$errorFound) {
			throw new Exception('Parse error tests failure.');
		}

		// `??`
		$errorFound = false;
		try {
			$this->db->buildQuery(
				'select * from users where name = \'J?ack\' and {block = ??}',
				[true]
			);
		} catch (ParseError) {
			$errorFound = true;
		}
		if (!$errorFound) {
			throw new Exception('Parse error tests failure.');
		}

		// `{{...}`
		$errorFound = false;
		try {
			$this->db->buildQuery(
				'select * from users where name = \'J?ack\' and {{block = ?}',
				[true]
			);
		} catch (ParseError) {
			$errorFound = true;
		}
		if (!$errorFound) {
			throw new Exception('Parse error tests failure.');
		}

		// `{...}}`
		$errorFound = false;
		try {
			$this->db->buildQuery(
				'select * from users where name = \'J?ack\' and {block = ?}}',
				[true]
			);
		} catch (ParseError) {
			$errorFound = true;
		}
		if (!$errorFound) {
			throw new Exception('Parse error tests failure.');
		}
	}
}
