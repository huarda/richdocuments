<?php

/**
 * ownCloud - Documents App
 *
 * @author Victor Dubiniuk
 * @copyright 2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Documents;

class Member extends Db{

	const DB_TABLE = '`*PREFIX*documents_member`';
	
	const ACTIVITY_THRESHOLD = 60; // 10 Minutes
	
	const MEMBER_STATUS_ACTIVE = 1;
	const MEMBER_STATUS_INACTIVE = 2;

	public static function add($esId, $uid, $color){
		$query = \OCP\DB::prepare('
			INSERT INTO ' . self::DB_TABLE . ' (`es_id`, `uid`, `color`, `last_activity`)
			VALUES (?, ?, ?, ?)
			');
		$query->execute(array(
			$esId,
			$uid,
			$color,
			time()
		));

		return \OCP\DB::insertid(self::DB_TABLE);
	}

	public static function getMember($id){
		$query = \OCP\DB::prepare('SELECT * FROM ' . self::DB_TABLE . ' WHERE `member_id`= ?');
		$result = $query->execute(array($id));
		return $result->fetchRow();
	}

	public static function getMembersAsArray($ids){
		$memberCount = count($ids);
		if (!$memberCount || !is_array($ids)){
			return array();
		}
		
		$stmt = self::buildPlaceholders($ids);
		$query = \OCP\DB::prepare('SELECT * FROM ' . self::DB_TABLE . ' WHERE `member_id` IN (' . $stmt . ')');
		$result = $query->execute($ids);
		return $result->fetchAll();
	}
	
	public static function updateMemberActivity($memberId){
		$query = \OCP\DB::prepare('UPDATE ' . self::DB_TABLE . ' SET `last_activity`=? WHERE `member_id`=?');
		$query->execute(array(
			time(),
			$memberId
		));
	}

	public static function getMembersByEsId($esId, $lastActivity = null){
		if (is_null($lastActivity)){
			$activeSince = self::getInactivityPeriod();
		} else {
			$activeSince = $lastActivity;
		}

		$query = \OCP\DB::prepare('SELECT * FROM ' . self::DB_TABLE . ' WHERE `es_id`= ? AND `last_activity` > ?');
		$result = $query->execute(array($esId, $activeSince));
		return $result->fetchAll();
	}
	
	/**
	 * Mark memebers as inactive
	 * @param string $esId - session Id
	 * @return array - list of memberId that were marked as inactive
	 */
	public static function cleanSession($esId){
		$time = self::getInactivityPeriod();

		$query = \OCP\DB::prepare('
			SELECT `member_id`
			FROM ' . self::DB_TABLE . '
			WHERE `es_id`= ?
				AND `last_activity`<?
				AND `status`=?
			');
		$result = $query->execute(array(
				$esId,
				$time,
				self::MEMBER_STATUS_ACTIVE
		));
		$deactivated = $result->fetchAll();
		
		self::deactivate($esId, $time);

		return $deactivated;
	}

	/**
	 * Update members to inactive state
	 * @param string $esId
	 * @param timestamp $time
	 */
	protected static function deactivate($esId, $time){
		$query = \OCP\DB::prepare('
			UPDATE ' . self::DB_TABLE . '
			SET `status`=?
			WHERE `es_id`=?
				AND `last_activity`<?
			');
		$query->execute(array(
			self::MEMBER_STATUS_INACTIVE,
			$esId,
			$time
		));
	}
	
	public static function deleteBySessionId($esId){
		$query = \OCP\DB::prepare('DELETE FROM ' . self::DB_TABLE . ' WHERE `es_id` = ?');
		$query->execute(array($esId));
	}
	
	protected static function getInactivityPeriod(){
		return time() - self::ACTIVITY_THRESHOLD;
	}

}
