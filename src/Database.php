<?php

namespace FpDbTest;

use mysqli;

readonly class Database implements DatabaseInterface
{
	private mysqli $mysqli;
	private QueryParser $parser;

	public function __construct(mysqli $mysqli)
	{
		$this->mysqli = $mysqli;
		$this->parser = new QueryParser($mysqli);
	}

	/**
	 * @throws ParseError
	 */
	public function buildQuery(string $query, array $args = []): string
	{
		return $this->parser->parse($query, $args, $this->skip());
	}

	public function skip(): mixed
	{
		return null;
	}
}
