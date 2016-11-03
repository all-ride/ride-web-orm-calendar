<?php

namespace ride\web\orm\table;

use ride\library\i18n\translator\Translator;
use ride\library\orm\model\Model;

use ride\web\orm\table\scaffold\ScaffoldTable;

/**
 * Table for the performances of an event
 */
class PerformanceTable extends ScaffoldTable {

    /**
     * Constructs a new scaffold table
     * @param \ride\library\orm\model\Model $model Model for the data of the
     * table
     * @param \ride\library\i18n\translator\Translator $translator Instance of
     * the translator
     * @param string $locale Code of the data locale
     * @param boolean $search False to disable search, True to search all
     * @param boolean|array $order False to disable order, True to order all
     * properties or an array with the fields to order
     * @return null
     */
    public function __construct(Model $model, Translator $translator, $locale = 'en', $search = true, $order = true) {
        parent::__construct($model, $translator, $locale, $search, $order);

        if (!$order) {
            return;
        }

        $this->orderStatements[$translator->translate('label.date.start')] = array(
            'ASC' => '{dateStart} ASC, {timeStart} ASC',
            'DESC' => '{dateStart} DESC, {timeStart} DESC',
        );

        $this->orderStatements[$translator->translate('label.date.stop')] = array(
            'ASC' => '{dateStop} ASC, {timeStop} ASC',
            'DESC' => '{dateStop} DESC, {timeStop} DESC',
        );
    }

}
