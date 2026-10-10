<?php

namespace App\Services\Trainer;

use RuntimeException;

/**
 * Отказ тренажёра, который можно показать человеку как есть: «попыток нет»,
 * «на сегодня тренировок достаточно». Текст уже переведён.
 */
class TrainerException extends RuntimeException
{
}
