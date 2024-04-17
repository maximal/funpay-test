<?php

namespace FpDbTest;

use InvalidArgumentException;
use mysqli;

/**
 * Парсер запросов для MySQL
 *
 * @author MaximAL
 * @since 2024-04-17
 */
final readonly class QueryParser
{
	public function __construct(private mysqli $mysqli)
	{
	}

	/**
	 * Разобрать запрос с параметрами и вернуть подготовленный для выполнения запрос MySQL.
	 *
	 * @param string $query запрос
	 * @param array $params параметры запроса
	 * @param mixed $skipValue значение, при наличии которого не нужно включать в запрос условный блок
	 * @return string Возвращает подготовленный к выполнению запрос MySQL.
	 * @throws ParseError
	 */
	public function parse(string $query, array $params = [], $skipValue = null): string
	{
		// Простой вариант: использовать замены строк
		// preg_match_all('/(\?d|\?f|\?a|\?#|\?)/i', $query, $matches)
		// или
		// str_replace(...)
		// и т. п.
		// но тогда мы будем заменять плейсхолдеры даже внутри литеральных строк.
		// По ТЗ непонятно, разрешены ли они внутри запросов, принимаем, что разрешены.
		// Построим простенький конечный автомат.
		// Комментарии по ТЗ запрещены, так что их не обрабатываем.

		// Нам нужно два состояния: токены и условные блоки
		// По ТЗ вложенных условных блоков нет, поэтому обойдёмся без стека

		$state = QueryParserState::General;
		$conditionalState = false;
		$conditionalBlock = [];
		$conditionalParams = [];

		$currentParam = 0;
		$result = [];

		//echo '[DEBUG] Parsing query: ', $query, PHP_EOL;
		foreach (mb_str_split($query) as $character) {
			/**
			 * @var ?string $token
			 * Текущий строковый токен, который будем добавлять
			 * либо к результирующей строке, либо к условному блоку
			 */
			$token = null;
			switch ($character) {
				case '\'':
					// Начало или конец строкового литерала
					$state = $state === QueryParserState::General
						? QueryParserState::StringToken : QueryParserState::General;
					$token = $character;
					break;

				case '?':
					if ($state === QueryParserState::General) {
						// Начало плейсхолдера для параметра
						$state = QueryParserState::PlaceholderToken;
					} elseif ($state === QueryParserState::PlaceholderToken) {
						// Двойной `??`
						throw new ParseError('Unexpected `?` after `?` in query: ' . $query);
					} else {
						$token = $character;
					}
					break;

				case 'd':
					if ($state === QueryParserState::PlaceholderToken) {
						// Плейсхолдер для целочисленного или NULL параметра
						$state = QueryParserState::General;
						$maybeNull = $this->getParam($query, $params, $currentParam++);
						if ($conditionalState) {
							$conditionalParams[] = $maybeNull;
						}
						$token = $this->autoParam($maybeNull !== null ? (int)$maybeNull : null);
					} else {
						$token = $character;
					}
					break;

				case 'f':
					if ($state === QueryParserState::PlaceholderToken) {
						// Плейсхолдер для вещественного или NULL параметра
						$state = QueryParserState::General;
						$maybeNull = $this->getParam($query, $params, $currentParam++);
						if ($conditionalState) {
							$conditionalParams[] = $maybeNull;
						}
						$token = $this->autoParam($maybeNull !== null ? (float)$maybeNull : null);
					} else {
						$token = $character;
					}
					break;

				case 'a':
					if ($state === QueryParserState::PlaceholderToken) {
						// Плейсхолдер для массива значений
						$state = QueryParserState::General;
						$param = $this->getParam($query, $params, $currentParam++);
						if ($conditionalState) {
							$conditionalParams[] = $param;
						}
						$token = $this->arrayParam($param);
					} else {
						$token = $character;
					}
					break;

				case '#':
					if ($state === QueryParserState::PlaceholderToken) {
						// Плейсхолдер для массива идентификаторов
						$param = $this->getParam($query, $params, $currentParam++);
						$state = QueryParserState::General;
						if ($conditionalState) {
							$conditionalParams[] = $param;
						}
						$token = $this->identifierParam($param);
					} else {
						$token = $character;
					}
					break;

				case '{':
					if (
						$state === QueryParserState::General ||
						$state === QueryParserState::PlaceholderToken
					) {
						if ($conditionalState) {
							// Вложенные условные блоки запрещены
							throw new ParseError('Nested conditional blocks are not allowed');
						}
						$conditionalState = true;
						$conditionalBlock = [];
						$conditionalParams = [];
						if ($state === QueryParserState::PlaceholderToken) {
							// Открытие условного блока сразу после `?`.
							// Закрываем плейсхолдер и открываем условный блок
							$result[] = $this->autoParam(
								$this->getParam($query, $params, $currentParam++)
							);
							$state = QueryParserState::General;
						}
					} else {
						$token = $character;
					}
					break;

				case '}':
					if (
						$state === QueryParserState::General ||
						$state === QueryParserState::PlaceholderToken
					) {
						if (!$conditionalState) {
							// Блок закрыт, но не был открыт
							throw new ParseError('Conditional block closing `}` found without opening `{`');
						}
						if ($state === QueryParserState::PlaceholderToken) {
							// Закрытие условного блока сразу после `?`.
							// Закрываем плейсхолдер и условный блок
							$state = QueryParserState::General;
							$param = $this->getParam($query, $params, $currentParam++);
							$conditionalParams[] = $param;
							$conditionalBlock[] = $this->autoParam($param);
						}
						$conditionalState = false;
						if ($this->includeConditional($conditionalParams, $skipValue)) {
							// Включаем условный блок
							array_push($result, ...$conditionalBlock);
						}
					} else {
						$token = $character;
					}
					break;

				//case '/':
				//case '-':
				//case '*':
				//	// Заготовка для открытия и закрытия комментариев
				//	// следующий `*` / `-`
				//	$state = $state !== QueryParserState::Comments
				//		? QueryParserState::Comments : $state;
				//	$state = $state === QueryParserState::Comments
				//		? QueryParserState::General : $state;
				//	break;

				default:
					if ($state === QueryParserState::PlaceholderToken) {
						// Мы внутри плейсхолдера `?`
						$param = $this->getParam($query, $params, $currentParam++);
						$state = QueryParserState::General;
						if ($conditionalState) {
							$conditionalParams[] = $param;
						}
						$token = $this->autoParam($param) . $character;
					} else {
						$token = $character;
					}
					break;
			}

			if ($token !== null) {
				if ($conditionalState) {
					// Токен внутри условного блока
					$conditionalBlock[] = $token;
				} else {
					// Токен вне условного блока
					$result[] = $token;
				}
			}
			//$stateTo = $state;
			//echo "\t", '[DEBUG] Current character @ state → state: ', $character, ' @ ';
			//echo $stateFrom->name, ' → ', $stateTo->name, PHP_EOL;
		}

		// Обрабатываем конец строки (там может остаться плейсхолдер или незакрытый блок)
		if ($conditionalState) {
			// Условный блок открыт, но не был закрыт
			throw new ParseError('Conditional block not closed at the end of query');
		}
		switch ($state) {
			case QueryParserState::StringToken:
				// Строковый литерал не закрыт
				throw new ParseError('String literal not closed at the end of query');

			case QueryParserState::PlaceholderToken:
				// Последний плейсхолдер в строке
				$result[] = $this->autoParam($this->getParam($query, $params, $currentParam));
				break;

			case QueryParserState::Comments:
			case QueryParserState::General:
				break;
		}

		return implode('', $result);
	}

	/**
	 * Взять значение из массива параметров по индексу;
	 * если такого индекса нет, выбросить исключение.
	 */
	private function getParam(string $query, array $params, int $index): mixed
	{
		// Не используем `isset()` потому что может быть `null`
		if (!array_key_exists($index, $params)) {
			throw new InvalidArgumentException(
				'No parameter with index ' . $index . ' for query: ' . $query
			);
		}
		return $params[$index];
	}

	private function autoParam(mixed $param): string
	{
		if ($param === null) {
			return 'null';
		}
		if (is_string($param)) {
			return '\'' . $this->mysqli->real_escape_string($param) . '\'';
		}
		if (is_int($param) || is_float($param)) {
			return '' . $param;
		}
		if (is_bool($param)) {
			return $param ? '1' : '0';
		}
		throw new InvalidArgumentException('Unsupported parameter type: ' . var_export($param, true));
	}

	/**
	 * @param string|string[] $identifiers
	 */
	private function identifierParam(string|array $identifiers): string
	{
		if (is_string($identifiers)) {
			return '`' . $identifiers . '`';
		}
		return implode(
			', ',
			array_map(static fn ($item) =>  '`' . $item . '`', $identifiers)
		);
	}

	/**
	 * @param list<mixed>|array<string,mixed> $values
	 */
	private function arrayParam(array $values): string
	{
		if (array_is_list($values)) {
			// Обычный список: значения
			return implode(
				', ',
				array_map(fn ($item) => $this->autoParam($item), $values)
			);
		}
		// Ассоциативный массив:
		// В ТЗ не совсем однозначно написано про этот случай
		// Принимаем, что это вариант в формате UPDATE-запроса:
		// идентификатор = значение, идентификатор = значение, ...
		$result = [];
		foreach ($values as $identifier => $value) {
			$result[] = $this->identifierParam((string)$identifier) . ' = ' . $this->autoParam($value);
		}
		return implode(', ', $result);
	}

	private function includeConditional(array $params, mixed $skipValue): bool
	{
		// Если хотя бы одно значение `$skipValue` найдено в массиве `$params`,
		// не включаем условный блок
		return !in_array($skipValue, $params, true);
	}
}
