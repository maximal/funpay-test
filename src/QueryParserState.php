<?php

namespace FpDbTest;

enum QueryParserState: int
{
	case General = 1;
	case StringToken = 2;
	case PlaceholderToken = 3;
	case Comments = 4;

	public function delimiters(): array
	{
		// TODO: Можно улучшить обработку разделяющих символов
		return [];
	}
}
