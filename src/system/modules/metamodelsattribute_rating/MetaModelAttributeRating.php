<?php

/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package    MetaModels
 * @subpackage AttributeRating
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

/**
 * This is the MetaModelAttribute class for handling numeric fields.
 *
 * @package    MetaModels
 * @subpackage AttributeRating
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 */
class MetaModelAttributeRating extends MetaModelAttributeComplex
{
	/**
	 * Returns all valid settings for the attribute type.
	 *
	 * @return array All valid setting names, this reensembles the columns in tl_metamodel_attribute
	 *               this attribute class understands.
	 */
	public function getAttributeSettingNames()
	{
		return array_merge(parent::getAttributeSettingNames(), array(
			'sortable',
			'rating_half',
			'rating_max',
			'rating_emtpy',
			'rating_full',
			'rating_hover',
		));
	}

	/**
	 * This generates the field definition for use in a DCA.
	 *
	 * It also sets the proper language variables (if not already set per dcaconfig.php or similar).
	 * Using the optional override parameter, settings known by this attribute can be overridden for the
	 * generating of the output array.
	 *
	 * @param array $arrOverrides The values to override, for a list of valid parameters, call
	 *                            getAttributeSettingNames().
	 *
	 * @return array The DCA array to use as $GLOBALS['TL_DCA']['tablename']['fields']['attribute-name]
	 *
	 * @codeCoverageIgnore
	 */
	public function getFieldDefinition($arrOverrides = array())
	{
		$arrFieldDef              = parent::getFieldDefinition($arrOverrides);
		$arrFieldDef['inputType'] = 'submit';
		return $arrFieldDef;
	}

	/**
	 * Retrieve the filter options of this attribute.
	 *
	 * Retrieve values for use in filter options, that will be understood by DC_ filter
	 * panels and frontend filter select boxes.
	 * One can influence the amount of returned entries with the two parameters.
	 * For the id list, the value "null" represents (as everywhere in MetaModels) all entries.
	 * An empty array will return no entries at all.
	 * The parameter "used only" determines, if only really attached values shall be returned.
	 * This is only relevant, when using "null" as id list for attributes that have preconfigured
	 * values like select lists and tags i.e.
	 *
	 * @param array $arrIds    The ids of items that the values shall be fetched from.
	 *
	 * @param bool  $usedOnly  Determines if only "used" values shall be returned.
	 *
	 * @param bool  &$arrCount Array for the counted values.
	 *
	 * @return array All options matching the given conditions as name => value.
	 */
	public function getFilterOptions($arrIds, $usedOnly, &$arrCount = null)
	{
		// TODO: unimplemented.
		return array();
	}

	/**
	 * Clean up the database.
	 *
	 * @return void
	 */
	public function destroyAUX()
	{
		Database::getInstance()
			->prepare('DELETE FROM tl_metamodel_rating WHERE mid=? AND aid=?')
			->execute($this->getMetaModel()->get('id'), $this->get('id'));
	}

	/**
	 * This method is called to retrieve the data for certain items from the database.
	 *
	 * @param int[] $arrIds The ids of the items to retrieve.
	 *
	 * @return mixed[] The nature of the resulting array is a mapping from id => "native data" where
	 *                 the definition of "native data" is only of relevance to the given item.
	 */
	public function getDataFor($arrIds)
	{
		$objData = Database::getInstance()
			->prepare(sprintf(
				'SELECT * FROM tl_metamodel_rating WHERE (mid=?) AND (aid=?) AND (iid IN (%s))',
				implode(', ', array_fill(0, count($arrIds), '?'))
			))
			->executeUncached(array_merge(array
				(
					$this->getMetaModel()->get('id'),
					$this->get('id')
				),
				$arrIds
			));

		$arrResult = array();
		while ($objData->next())
		{
			$arrResult[$objData->iid] = array
			(
				'votecount' => intval($objData->votecount),
				'meanvalue' => floatval($objData->meanvalue),
			);
		}
		foreach (array_diff($arrIds, array_keys($arrResult)) as $intId)
		{
			$arrResult[$intId] = array
			(
				'votecount' => 0,
				'meanvalue' => 0,
			);
		}

		return $arrResult;
	}

	// @codingStandardsIgnoreStart - we know that this is a non-op just to be non-abstract.
	/**
	 * This method is a no-op in this class.
	 *
	 * @param mixed[int] $arrValues Unused.
	 *
	 * @return void
	 *
	 * @codeCoverageIgnore
	 */
	public function setDataFor($arrValues)
	{
		// No op - this attribute is not meant to be manipulated.
	}
	// @codingStandardsIgnoreEnd

	/**
	 * Delete all votes for the given items.
	 *
	 * @param int[] $arrIds The ids of the items to remove votes for.
	 *
	 * @return void
	 */
	public function unsetDataFor($arrIds)
	{
		Database::getInstance()
			->prepare(sprintf(
				'DELETE FROM tl_metamodel_rating WHERE mid=? AND aid=? AND (iid IN (%s))',
				implode(', ', array_fill(0, count($arrIds), '?'))))
			->execute(array_merge(array
				(
					$this->getMetaModel()->get('id'),
					$this->get('id')
				),
				$arrIds
			));
	}

	/**
	 * Calculate the lock id for a given item.
	 *
	 * @param int $intItemId The id of the item.
	 *
	 * @return string
	 */
	protected function getLockId($intItemId)
	{
		return sprintf('vote_lock_%s_%s_%s',
			$this->getMetaModel()->get('id'),
			$this->get('id'),
			$intItemId
		);
	}

	/**
	 * Add a vote to the database.
	 *
	 * @param int   $intItemId The id of the item to be voted.
	 *
	 * @param float $fltValue  The value of the vote.
	 *
	 * @param bool  $blnLock   Flag if the user session shall be locked against voting for this item again.
	 *
	 * @return void
	 */
	public function addVote($intItemId, $fltValue, $blnLock = false)
	{
		if (Session::getInstance()->get($this->getLockId($intItemId)))
		{
			return;
		}

		$arrData = $this->getDataFor(array($intItemId));

		if (!$arrData || !$arrData[$intItemId]['votecount'])
		{
			$voteCount   = 0;
			$prevPercent = 0;
		}
		else
		{
			$voteCount   = $arrData[$intItemId]['votecount'];
			$prevPercent = floatval($arrData[$intItemId]['meanvalue']);
		}

		$grandTotal = ($voteCount * $this->get('rating_max') * $prevPercent);
		$hundred    = ($this->get('rating_max') * (++$voteCount));

		// Calculate the percentage.
		$value = (1 / $hundred * ($grandTotal + $fltValue));

		$arrSet = array
		(
			'mid' => $this->getMetaModel()->get('id'),
			'aid' => $this->get('id'),
			'iid' => $intItemId,
			'votecount' => $voteCount,
			'meanvalue' => $value,
		);

		if (!$arrData || !$arrData[$intItemId]['votecount'])
		{
			$strSQL = 'INSERT INTO tl_metamodel_rating %s';
		}
		else
		{
			$strSQL = 'UPDATE tl_metamodel_rating %s WHERE mid=? AND aid=? AND iid=?';
		}

		Database::getInstance()
			->prepare($strSQL)
			->set($arrSet)
			->execute(
				$this->getMetaModel()->get('id'),
				$this->get('id'),
				$intItemId
			);

		if ($blnLock)
		{
			Session::getInstance()->set($this->getLockId($intItemId), true);
		}
	}

	/**
	 * Test whether the given image exists.
	 *
	 * @param string $strImage   Path to the image to use.
	 *
	 * @param string $strDefault Path to the fallback image.
	 *
	 * @return string If the image exists, the image is returned, the default otherwise.
	 */
	protected function ensureImage($strImage, $strDefault)
	{
		if (strlen($strImage) && file_exists(TL_ROOT . '/' . $strImage))
		{
			return $strImage;
		}

		return $strDefault;
	}

	/**
	 * Initialize the template with values.
	 *
	 * @param MetaModelTemplate               $objTemplate The Template instance to populate.
	 *
	 * @param array                           $arrRowData  The row data for the current item.
	 *
	 * @param MetaModelRenderSettingAttribute $objSettings The render settings to use for this attribute.
	 *
	 * @return void
	 */
	public function prepareTemplate(MetaModelTemplate $objTemplate, $arrRowData, $objSettings = null)
	{
		parent::prepareTemplate($objTemplate, $arrRowData, $objSettings);

		$base = Environment::getInstance()->base;

		$strEmpty = $this->ensureImage(
			$this->get('rating_emtpy'),
			'system/modules/metamodelsattribute_rating/html/star-empty.png'
		);
		$strFull  = $this->ensureImage(
			$this->get('rating_full'),
			'system/modules/metamodelsattribute_rating/html/star-full.png'
		);
		$strHover = $this->ensureImage(
			$this->get('rating_hover'),
			'system/modules/metamodelsattribute_rating/html/star-hover.png'
		);

		$size                    = getimagesize(TL_ROOT . '/' . $strEmpty);
		$objTemplate->imageWidth = $size[0];
		$objTemplate->rateHalf   = $this->get('rating_half') ? 'true' : 'false';
		$objTemplate->name       = 'rating_attribute_'.$this->get('id') . '_' . $arrRowData['id'];

		$objTemplate->ratingDisabled = (
			(TL_MODE == 'BE')
			|| $objSettings->get('rating_disabled')
			|| Session::getInstance()->get($this->getLockId($arrRowData['id']))
		);

		$value = ($this->get('rating_max') * floatval($arrRowData[$this->getColName()]['meanvalue']));

		$objTemplate->currentValue = (round(($value / .5), 0) * .5);
		$objTemplate->tipText      = sprintf(
			$GLOBALS['TL_LANG']['metamodel_rating_label'],
			'[VALUE]',
			$this->get('rating_max')
		);
		$objTemplate->ajaxUrl      = sprintf('SimpleAjax.php?metamodelsattribute_rating=%s', $this->get('id'));
		$objTemplate->ajaxData     = json_encode(array
			(
				'id' => $this->get('id'),
				'pid' => $this->get('pid'),
				'item' => $arrRowData['id']
			)
		);

		$arrOptions = array();
		$intInc     = strlen($this->get('rating_half')) ? .5 : 1;
		$intValue   = $intInc;

		while ($intValue <= $this->get('rating_max'))
		{
			$arrOptions[] = $intValue;
			$intValue    += $intInc;
		}
		$objTemplate->options = $arrOptions;

		$objTemplate->imageEmpty = $base . $strEmpty;
		$objTemplate->imageFull  = $base . $strFull;
		$objTemplate->imageHover = $base . $strHover;
	}

	/**
	 * Sorts the given array list by field value in the given direction.
	 *
	 * @param int[]  $arrIds       A list of Ids from the MetaModel table.
	 *
	 * @param string $strDirection The direction for sorting. either 'ASC' or 'DESC', as in plain SQL.
	 *
	 * @return int[] The sorted integer array.
	 */
	public function sortIds($arrIds, $strDirection)
	{
		$objData = Database::getInstance()
			->prepare(sprintf(
				'SELECT iid FROM tl_metamodel_rating WHERE (mid=?) AND (aid=?) AND (iid IN (%s)) ORDER BY meanvalue '
				. $strDirection,
				implode(', ', array_fill(0, count($arrIds), '?'))
			))
			->execute(array_merge(array
				(
					$this->getMetaModel()->get('id'),
					$this->get('id')
				),
				$arrIds
			));

		$arrSorted = $objData->fetchEach('iid');

		return ($strDirection == 'DESC')
			? array_merge($arrSorted, array_diff($arrIds, $arrSorted))
			: array_merge(array_diff($arrIds, $arrSorted), $arrSorted);
	}
}
