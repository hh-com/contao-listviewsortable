<?php


class ListViewSortable extends System
{
	public function injectJavascript($table)
	{
		$GLOBALS['TL_MOOTOOLS'][] = '<script>let contaoRequestToken = "'.REQUEST_TOKEN.'";</script>';
			
		if($GLOBALS['TL_DCA'][$table]['list']['sorting']['listViewSortable'] && !$this->Input->get('act') && !isset($GLOBALS['listViewSortable_inserted']))
		{
		
			$GLOBALS['TL_DCA'][$table]['list']['sorting']['flag'] = 7;
			$GLOBALS['TL_DCA'][$table]['list']['sorting']['mode'] = 1;
		
		}

		// we save the first call of this function, cause this is the "main" table
		// later on, if a child or parent table gets loaded we dont want to include the scripts
		$GLOBALS['listViewSortable_inserted'] = true;
		
		if($GLOBALS['TL_DCA'][$table]['list']['sorting']['listViewSortable'])
		{
			$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaolistviewsortable/js/dragSort.js';
		}

	}

	public function resort($strAction, DataContainer $dc)
	{
		$table = $dc->table;
		
		if($this->Input->post('table'))
		{
			// check if this table belongs to current module
			foreach($GLOBALS['BE_MOD'] as $category)
			{
				foreach($category as $module => $data)
				{
					if($module == $this->Input->get('do'))
					{
						if(in_array($this->Input->post('table'),$data['tables']))
						{
							$table = $this->Input->post('table');
						}
						break 2;
					}
				}
			}
		}
		
	    if ($strAction == 'listViewSortable')
	    {

			$this->import('Database');

			if(!preg_match("~^\d+$~",$this->Input->post('id')))
			{
				die('Error: erroneous id');
			}
			
			if ($this->Input->post('afterid') || $this->Input->post('beforeid'))
			{

				$beforeid = $this->Input->post('beforeid');
				$afterid = $this->Input->post('afterid');
				
				$chosenRecord = $this->Database->prepare("SELECT * FROM " . $table . " WHERE id=?")
									->limit(1)
									->executeUncached($this->Input->post('id'));
				// If list is grouped
				if ($GLOBALS['TL_DCA']['tl_references']['list']['sorting']['disableGrouping'] == false) {
					$group = explode(" ", $GLOBALS['TL_DCA']['tl_references']['list']['sorting']['fields'][0])[0];
				} else {
					$group = false;
				}
				

				if ($group) {
					$groupBy = " `".$group."` like '".$chosenRecord->$group."'" ;
				} else {
					$groupBy = "" ;
				}

				#$itemBefore = $this->getOneBefore($chosenRecord->sorting, $table, $groupBy);
				#$itemAfter = $this->getOneAfter($chosenRecord->sorting, $table, $groupBy);

				$firstOfGroup = $this->getFirstOfGroup($table, $groupBy);
				#$lastOfGroup = $this->getLastOfGroup($table, $groupBy);


				$movedInsideGroup = $this->movedInsideGroup($beforeid, $afterid, $table, $groupBy);

				/**
				 * Move it to the Top of the Group
				 */
				if ($movedInsideGroup == false) {
					$this->Database->prepare("UPDATE " . $table . " SET sorting=? WHERE id=?")
										->execute(intval($firstOfGroup['sorting'] + 128),$this->Input->post('id'));
				} else {
					if ($beforeid) {
						$this->moveItToItemBefore($beforeid, $table, $chosenRecord->id );
					} else {
						$this->moveItToItemAfter($afterid, $table, $chosenRecord->id );
					}
				}

				// reorganize all 
				$this->reArrangeAll($table);

				echo json_encode( [ 'status' => 'done']);
			
				// echo json_encode( 
				// 	[
				// 		"chosen" => $chosenRecord->id,
				// 		"before" => $itemBefore['id'],
				// 		"after" => $itemAfter['id'],
				// 		"beforeid" => $beforeid,
				// 		"afterid" => $afterid,
				// 		"firstOfGroup" => $firstOfGroup['id'],
				// 		"lastOfGroup" => $lastOfGroup['id'],
				// 		"isMovedInGroup" => $movedInsideGroup,
				// 	]
				// ) ;
				die();
			}
	    }
	}

	/**
	 * get one item after the chosen one
	 */
	public function getOneBefore($sortingFromChosen, $table, $groupBy = "")
	{
		if ($groupBy != "") {
			$groupBy = " AND " . $groupBy;
		}

		$oneBefore = \Database::getInstance()->prepare("SELECT * FROM " . $table . " WHERE sorting > ? ".$groupBy." ORDER BY sorting ASC")
		->limit(1)
		->execute($sortingFromChosen);

		if ($oneBefore->numRows > 0)
		{
			return $oneBefore->row();
		} else {
			return false;
		}
	}

	/**
	 * get one item after the chosen one
	 */
	public function getOneAfter($sortingFromChosen, $table, $groupBy = "")
	{
		if ($groupBy != "") {
			$groupBy = " AND " . $groupBy;
		}

		$oneAfter = \Database::getInstance()->prepare("SELECT * FROM " . $table . " WHERE sorting < ? ".$groupBy." ORDER BY sorting DESC")
		->limit(1)
		->execute($sortingFromChosen);

		if ($oneAfter->numRows > 0)
		{
			return $oneAfter->row();
		} else {
			return false;
		}
	}

	/**
	 * get the first item of a group
	 */
	public function getFirstOfGroup($table, $groupBy = "")
	{
		if ($groupBy != "") {
			$groupBy = " WHERE " . $groupBy;
		}

		$first = \Database::getInstance()->prepare("SELECT * FROM " . $table . " ".$groupBy." ORDER BY sorting DESC")
		->limit(1)
		->execute();

		if ($first->numRows > 0)
		{
			return $first->row();
		} else {
			return false;
		}
	}

	/**
	 * get the last item of a group
	 */
	public function getLastOfGroup($table, $groupBy = "")
	{
		if ($groupBy != "") {
			$groupBy = " WHERE " . $groupBy;
		}
		$last = \Database::getInstance()->prepare("SELECT * FROM " . $table . " ".$groupBy." ORDER BY sorting ASC")
		->limit(1)
		->execute();

		if ($last->numRows > 0)
		{
			return $last->row();
		} else {
			return false;
		}
	}

	/**
	 * Check if item is moved inside a Group
	 */
	public function movedInsideGroup($beforeid, $afterid, $table, $groupBy)
	{
		if ($groupBy != "") {
			$groupBy = " AND " . $groupBy;
		}

		if ($beforeid) {
			$whereId = "id = " . $beforeid;
		}
		if ($afterid) {
			$whereId = "id = " . $afterid;
		}

		$isInGroup = \Database::getInstance()->prepare("SELECT * FROM " . $table . " WHERE ".$whereId."  ".$groupBy." ")
		->limit(1)
		->execute();

		if ($isInGroup->numRows > 0)
		{
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Move an item to another place before
	 */
	public function moveItToItemBefore($beforeid, $table, $chosenId)
	{
		$itemBefore = \Database::getInstance()->prepare("SELECT * FROM " . $table . " WHERE id = ? ORDER BY sorting ASC")
		->limit(1)
		->execute($beforeid);

		if ($itemBefore->numRows > 0)
		{
			$itemAfterArr = $itemBefore->row();
			$qry = $this->Database->prepare("UPDATE " . $table . " SET sorting=? WHERE id=?")
			->execute(intval($itemAfterArr['sorting'] + 64),$chosenId);

			return true;

		} else {
			return false;
		}
	}

	/**
	 * Move an item to another place after
	 */
	public function moveItToItemAfter($afterid, $table, $chosenId)
	{
		$itemAfter = \Database::getInstance()->prepare("SELECT * FROM " . $table . " WHERE id = ?  ORDER BY sorting ASC")
		->limit(1)
		->execute($afterid);

		if ($itemAfter->numRows > 0)
		{
			$itemAfterArr = $itemAfter->row();
			$qry = $this->Database->prepare("UPDATE " . $table . " SET sorting=? WHERE id=?")
			->execute(intval($itemAfterArr['sorting'] - 64), $chosenId);

			return true;

		} else {
			return false;
		}
	}

	/**
	 * Reorganize all after change order
	 */
	public function reArrangeAll($table)
	{
		$allObj = \Database::getInstance()->prepare("SELECT * FROM " . $table . " ORDER BY sorting ASC")
		->execute()
		->fetchAllAssoc();

		$sortingValue = 1280;

		foreach ($allObj as $row) {

			$qry = $this->Database->prepare("UPDATE " . $table . " SET sorting=? WHERE id=?")
			->execute($sortingValue ,$row['id']);

			$sortingValue = $sortingValue + 128;
		}

	}
	
}