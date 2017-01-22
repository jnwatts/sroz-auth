<?php

namespace Auth;

class DbHelper {
	public static function initTimestamp($query) {
		return $query->setValue("created_at", "CURRENT_TIMESTAMP")
			->setValue("updated_at", "CURRENT_TIMESTAMP");
	}
	public static function updateTimestamp($query) {
		return $query->set("updated_at", "CURRENT_TIMESTAMP");
	}
	public static function whereTimestampWithin($query, $seconds) {
		return "updated_at > CURRENT_TIMESTAMP - " . $query->createNamedParameter($seconds);
	}
}