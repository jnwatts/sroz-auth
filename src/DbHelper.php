<?php

namespace Auth;

class DbHelper {
	public static function initLastModified($query) {
		return $query->setValue("lastModified", "CURRENT_TIMESTAMP");
	}
	public static function updateLastModified($query) {
		return $query->set("lastModified", "CURRENT_TIMESTAMP");
	}
}