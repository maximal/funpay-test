<?php
/**
 * Заполнение таблицы `users` в БД MySQL.
 *
 * @author MaximAL
 * @since 2024-04-17
 */


exit((new Seeder())->run());


readonly class Seeder
{
	public function run(): int
	{
		echo 'Running database seeder...', PHP_EOL;
		echo 'We’re ', getenv('IN_DOCKER') ? 'In Docker' : 'Not in Docker', PHP_EOL;
		echo 'Database credentials: ', implode(', ', $this->getDatabaseCredentials()), PHP_EOL;

		// Подключаемся
		do {
			try {
				$mysqli = new mysqli(...$this->getDatabaseCredentials());
			} catch (Throwable $exception) {
				$mysqli = null;
				echo 'Database connection failed: ', $exception->getMessage(), PHP_EOL;
				echo 'Retrying in 5 seconds...', PHP_EOL;
				sleep(5);
			}
		} while ($mysqli === null);

		// Очищаем таблицу
		$mysqli->execute_query('DROP TABLE IF EXISTS `users`');
		echo '`users` table dropped', PHP_EOL;

		// Создаём таблицу
		$mysqli->execute_query('CREATE TABLE IF NOT EXISTS `users` (
			`user_id` int(11) PRIMARY KEY AUTO_INCREMENT,
			`name` varchar(255) NOT NULL,
			`email` varchar(255) DEFAULT NULL,
			`block` tinyint(1) DEFAULT 0
		)');
		echo '`users` table created', PHP_EOL;

		// Заполняем таблицу
		$mysqli->execute_query(
			'INSERT INTO `users`
				(`user_id`, `name`, `email`, `block`)
			VALUES
				(-1, \'Sasha -1\', \'sasha+negative+1@maximals.ru\', 0),
				(1, \'Sasha 1\', \'sasha+1@maximals.ru\', 0),
				(2, \'Sasha 2\', \'sasha+1@maximals.ru\', 1),
				(3, \'Sasha 3\', \'sasha+1@maximals.ru\', 0),
				(4, \'Sasha 4\', \'sasha+1@maximals.ru\', 1),
				(5, \'Sasha 5\', \'sasha+1@maximals.ru\', 0),
				(6, \'Sasha 6\', \'sasha+6@maximals.ru\', 1),
				(7, \'Sasha 7 \'\' apostrophe\', \'sasha+6@maximals.ru\', 1),
				(8, \'Jack\', \'jack@maximals.ru\', 0)'
		);
		echo '`users` table seeded', PHP_EOL;

		// Проверяем количество записей
		$count = $mysqli->query('SELECT COUNT(*) as `count` FROM `users`')->fetch_assoc();
		echo '`users` table records count: ', $count['count'], PHP_EOL;

		echo 'Database seeding OK', PHP_EOL;
		return 0;
	}

	private function getDatabaseCredentials(): array
	{
		return getenv('IN_DOCKER')
			? ['database', 'root', 'password', 'database', 3306]
			: ['127.0.0.1', 'root', 'password', 'database', 3306];
	}
}
