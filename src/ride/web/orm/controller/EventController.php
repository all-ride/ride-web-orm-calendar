<?php

namespace ride\web\orm\controller;

use ride\application\orm\calendar\entry\EventRepeaterEntry;

use ride\library\http\Header;
use ride\library\i18n\I18n;
use ride\library\orm\entry\format\EntryFormatter;
use ride\library\orm\model\Model;
use ride\library\security\exception\UnauthorizedException;
use ride\library\validation\constraint\ConditionalConstraint;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\factory\ValidationFactory;

use ride\service\OrmService;

use ride\web\orm\form\ScaffoldComponent;
use ride\web\orm\form\EventRepeaterComponent;
use ride\web\orm\table\scaffold\decorator\DataDecorator;
use ride\web\orm\table\scaffold\decorator\LocalizeDecorator;
use ride\web\orm\table\scaffold\ScaffoldTable;
use ride\web\WebApplication;

/**
 * Controller for the events
 */
class EventController extends ScaffoldController {

    /**
     * Action to set a form with a data object to the view
     * @param \ride\library\i18n\I18n $i18n
     * @param string $locale Locale code of the data
     * @param integer $id Primary key of the data object
     * @return null
     */
    public function detailAction(I18n $i18n, $locale, $id) {
        // resolve locale
        $this->locale = $i18n->getLocale($locale)->getCode();

        // resolve entry
        if (!$this->isReadable($id)) {
            throw new UnauthorizedException();
        }

        $entry = $this->getEntry($id);
        if (!$entry) {
            $this->response->setNotFound();

            return;
        }

        $meta = $this->model->getMeta();

        // format entry for title
        $format = $meta->getFormat(EntryFormatter::FORMAT_TITLE);
        $entryFormatter = $this->orm->getEntryFormatter();
        $title = $entryFormatter->formatEntry($entry, $format);

        $translator = $this->getTranslator();

        // performance table
        $this->model = $this->orm->getEventPerformanceModel();
        $locales = $i18n->getLocaleCodeList();
        $imageUrlGenerator = $this->dependencyInjector->get('ride\\library\\image\\ImageUrlGenerator');

        $urlBase = $this->getUrl('calendar.event.detail', array(
            'locale' => $this->locale,
            'id' => $id,
        ));
        $urlPerformanceEdit = $this->getUrl('calendar.performance.edit', array(
            'locale' => $this->locale,
            'event' => $id,
            'id' => '%id%',
        )) . '?referer=' . urlencode($this->request->getUrl());

        $dataDecorator = new DataDecorator($this->model, null, $urlPerformanceEdit, 'id');
        $dataDecorator->mapProperty('title', '{dateString}');

        $table = new ScaffoldTable($this->model, $this->getTranslator(), $this->locale, true, true);
        $table->addDecorator($dataDecorator);
        if ($this->model->getMeta()->isLocalized()) {
            $table->addDecorator(new LocalizeDecorator($this->model, $urlPerformanceEdit, $this->locale, $locales));
        }
        $table->getModelQuery()->addCondition('{event} = %1%', $id);
        $table->addAction(
            $translator->translate('button.delete'),
            array($this, 'deletePerformance'),
            $translator->translate('label.table.confirm.delete')
        );

        $form = $this->processTable($table, $urlBase, 10, $this->orderMethod, $this->orderDirection);
        if ($this->response->willRedirect() || $this->response->getView()) {
            return;
        }

        // url's
        $urlBack = $this->request->getQueryParameter('referer');
        if (!$urlBack) {
            $urlBack = $this->getAction(self::ACTION_INDEX);
        }

        $urlPerformanceAdd = $this->getUrl('calendar.performance.add', array(
            'locale' => $locale,
            'event' => $id,
        ));

        // referer to append to urls
        $urlReferer = '?referer=' . urlencode($this->request->getUrl());

        // set template and vars as response
        $view = $this->setTemplateView('orm/scaffold/detail.event', array(
            'meta' => $meta,
            'title' => $title,
            'entry' => $entry,
            'editUrl' => $this->getAction(self::ACTION_EDIT, array('id' => $id)) . $urlReferer,
            'backUrl' => $urlBack,
            'addPerformanceUrl' => $urlPerformanceAdd . $urlReferer,
            'form' => $form->getView(),
            'table' => $table,
            'locales' => $locales,
            'locale' => $locale,
            'localizeUrl' => $this->getAction(self::ACTION_DETAIL, array('locale' => '%locale%', 'id' => $id)),
        ));

        $form->processView($view);
    }

    /**
     * Action to delete the performance entries from the model
     * @param array $entries Array of entries or entry primary keys
     * @return null
     */
    public function deletePerformance($entries) {
        if (!$entries || !$this->isDeletable()) {
            return;
        }

        $entryFormatter = $this->orm->getEntryFormatter();
        $format = $this->model->getMeta()->getFormat(EntryFormatter::FORMAT_TITLE);

        foreach ($entries as $entry) {
            if (is_numeric($entry)) {
                $entryId = $entry;
            } else {
                $entryId = $entry->id;
            }

            if (!$this->isDeletable($entryId, false)) {

            } else {
                try {
                    if (is_numeric($entry)) {
                        $entry = $this->model->createProxy($entry);
                    }

                    $entry = $this->model->delete($entry);

                    $this->addSuccess('success.data.deleted', array('data' => $entryFormatter->formatEntry($entry, $format)));
                } catch (ValidationException $exception) {
                    $errors = $exception->getAllErrors();
                    foreach ($errors as $fieldName => $fieldErrors) {
                        foreach ($fieldErrors as $fieldError) {
                            $this->addError($fieldError->getCode(), $fieldError->getParameters());
                        }
                    }
                }
            }
        }

        $referer = $this->request->getHeader(Header::HEADER_REFERER);
        if (!$referer) {
            $referer = $this->request->getUrl();
        }

        $this->response->setRedirect($referer);
    }

    /**
     * Action to add or edit a performance
     * @param \ride\library\i18n\I18n $i18n
     * @param string $locale Locale code of the data
     * @param string $event Id of the event
     * @param string $performance Id of the performance
     * @return null
     */
    public function performanceFormAction(I18n $i18n, $locale, WebApplication $web, OrmService $ormService, EventRepeaterComponent $repeaterComponent, $event, $id = null) {
        // resolve locale
        $this->locale = $i18n->getLocale($locale)->getCode();

        // resolve event
        if (!$this->isReadable($event)) {
            throw new UnauthorizedException();
        }

        $event = $this->getEntry($event);
        if (!$event) {
            $this->response->setNotFound();

            return;
        }

        // resolve performance
        $this->model = $this->orm->getEventPerformanceModel();
        $repeaterModel = $this->orm->getEventRepeaterModel();

        if ($id) {
            $performance = $this->getEntry($id);
            if (!$performance || $performance->getEvent()->getId() !== $event->getId()) {
                $this->response->setNotFound();

                return;
            }
        } else {
            $performance = $this->createEntry();
            $performance->event = $event;
        }

        // prepare data for form
        if ($performance->repeater) {
            $repeater = clone $performance->repeater;
        } else {
            $repeater = $repeaterModel->createEntry();
        }

        $repeater->dateStart = $performance->dateStart;
        $repeater->timeStart = $performance->timeStart;
        $repeater->dateStop = $performance->dateStop;
        $repeater->timeStop = $performance->timeStop;
        $repeater->isDay = $performance->isDay;
        $repeater->isPeriod = $performance->isPeriod;
        if ($repeater->id) {
            if (!$performance->isRepeaterEdited()) {
                $repeater->isRepeat = true;
            }
            if ($repeater->mode === EventRepeaterEntry::MODE_WEEKLY && $repeater->modeDetail) {
                $repeater->weekly = explode(',', $repeater->modeDetail);
            } elseif ($repeater->mode === EventRepeaterEntry::MODE_MONTHLY) {
                $repeater->monthly = $repeater->modeDetail;
            }
        }

        $data = array(
            'performance' => clone $performance,
            'date' => $repeater,
        );

        // create form
        $translator = $this->getTranslator();

        $performanceComponent = new ScaffoldComponent($web, $this->getSecurityManager(), $ormService, $this->model);
        $performanceComponent->setLocale($locale);
        $performanceComponent->omitField('event');
        $performanceComponent->omitField('dateStart');
        $performanceComponent->omitField('timeStart');
        $performanceComponent->omitField('dateStop');
        $performanceComponent->omitField('timeStop');
        $performanceComponent->omitField('isPeriod');
        $performanceComponent->omitField('isDay');
        $performanceComponent->omitField('repeater');
        $performanceComponent->omitField('isRepeaterEdited');

        $form = $this->createFormBuilder($data);
        $form->setId('form-event-performance');
        $form->addRow('date', 'component', array(
            'label' => $translator->translate('label.date'),
            'component' => $repeaterComponent,
            'embed' => true,
        ));
        $form->addRow('performance', 'component', array(
            'label' => $translator->translate('label.performance'),
            'component' => $performanceComponent,
            'embed' => true,
        ));
        $form->addRow('editMode', 'option', array(
            'default' => 'instance',
            'options' => array(
                'instance' => 'Instance',
                'future' => 'Future',
                'all' => 'All',
            ),
        ));
        $form->addRow('ignoreEdited', 'option', array(
            'default' => true,
        ));
        $form = $form->build();

        // handle form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                // obtain performance from form
                $data = $form->getData();
                $data['performance']->event = $event;
                $data['performance']->dateStart = $data['date']->dateStart;
                $data['performance']->timeStart = $data['date']->timeStart;
                $data['performance']->timeStop = $data['date']->timeStop;
                $data['performance']->isDay = $data['date']->isDay;
                $data['performance']->isPeriod = $data['date']->isPeriod;
                if ($data['date']->isPeriod) {
                    $data['performance']->dateStop = $data['date']->dateStop;
                } else {
                    $data['performance']->dateStop = null;
                }

                if ($data['date']->isRepeat) {
                    // obtain repeater
                    $repeaterModel = $this->orm->getEventRepeaterModel();

                    $repeater->setMode($data['date']->mode);
                    if ($data['date']->mode === EventRepeaterEntry::MODE_MONTHLY) {
                        $repeater->setModeDetail($data['date']->monthly);
                    } else {
                        $repeater->setModeDetail(implode(',', $data['date']->weekly));
                    }

                    $repeater->setStep($data['date']->step);
                    if ($data['date']->until === 'date') {
                        $repeater->setDateUntil($data['date']->dateUntil);
                    } else {
                        $repeater->setOccurences($data['date']->occurences);
                    }

                    if (!$performance->repeater) {
                        // new repeater
                        $repeaterModel->save($repeater);

                        // get the difference between start and stop date to set
                        // the same interval for the repeated dates
                        $diff = null;
                        if ($data['performance']->dateStop !== null) {
                            $diff = $data['performance']->dateStop - $data['performance']->dateStart;
                        }

                        // create performances for every date in repeater
                        $dates = $repeater->getDates($data['date']->dateStart);
                        foreach ($dates as $date) {
                            if ($performance->id && $date === $performance->dateStart) {
                                continue;
                            }

                            $performance = clone $data['performance'];
                            $performance->id = 0;
                            $performance->version = 0;
                            $performance->repeater = $repeater;
                            $performance->dateStart = $date;

                            if ($diff !== null) {
                                $performance->dateStop = $date + $diff;
                            }

                            $this->model->save($performance);
                        }
                    } elseif ($performance->dateStart == $data['performance']->dateStart && $performance->dateStop == $data['performance']->dateStop && $performance->repeater->equals($repeater)) {
                        // repeater is unchanged
                        switch ($data['editMode']) {
                            case 'all':
                                $query = $this->model->createQuery($locale);
                                $query->addCondition('{event} = %1%', $event->getId());
                                if ($data['ignoreEdited']) {
                                    $query->addCondition('{isRepeaterEdited} <> 1');
                                }

                                $performances = $query->query();

                                break;
                            case 'future':
                                $query = $this->model->createQuery($locale);
                                $query->addCondition('{event} = %1%', $event->getId());
                                $query->addCondition('{dateStart} >= %1%', time());
                                if ($data['ignoreEdited']) {
                                    $query->addCondition('{isRepeaterEdited} <> 1');
                                }

                                $performances = $query->query();

                                break;
                            default: // instance
                                $data['performance']->isRepeaterEdited = true;

                                $performances = array($data['performance']);

                                break;
                        }

                        $fields = $performanceComponent->getFields();
                        $fields['timeStart'] = 'timeStart';
                        $fields['timeStop'] = 'timeStop';

                        foreach ($performances as $performance) {
                            foreach ($fields as $field) {
                                $performance->$field = $data['performance']->$field;
                            }

                            $this->model->save($performance);
                        }
                    } elseif ($data['editMode'] === 'instance') {
                        // updated repeater, but only for current instance, don't touch repeater
                        $data['performance']->repeater = $performance->repeater;
                        $data['performance']->isRepeaterEdited = true;

                        $this->model->save($data['performance']);
                    } else {
                        // updated repeater
                        $repeaterModel->save($repeater);

                        $query = $this->model->createQuery($locale);
                        $query->addCondition('{event} = %1%', $event->getId());
                        $query->addOrderBy('{dateStart} ASC, {timeStart} ASC');

                        if ($data['editMode'] !== 'all') {
                            $query->addCondition('{dateStart} > %1%', time());
                        }

                        $performances = $query->query();

                        $properties = $performanceComponent->getRowNames();
                        $properties['timeStart'] = 'timeStart';
                        $properties['timeStop'] = 'timeStop';

                        $dates = $repeater->getDates($data['performance']->dateStart);

                        $occured = 0;
                        $time = time();
                        foreach ($performances as $performance) {
                            $occured++;

                            if ($performance->dateStart < $time || ($data['ignoreEdited'] && $performance->isRepeaterEdited())) {
                                // passed performance, not touching it
                                continue;
                            }

                            // update non date fields
                            foreach ($properties as $property) {
                                $performance->$property = $data['performance']->$property;
                            }

                            if ($repeater->occurences !== null && $occured > $repeater->occurences) {
                                // decreased occurences
                                $this->model->delete($performance);
                            } else {
                                // update date
                                $date = array_shift($dates);

                                $diff = null;
                                if ($performance->dateStop !== null) {
                                    $diff = $performance->dateStop - $performance->dateStart;
                                }

                                $performance->dateStart = $date;
                                if ($diff !== null) {
                                    $performance->dateStop = $date + $diff;
                                }

                                $this->model->save($performance);
                            }
                        }

                        // get the difference between start and stop date to set
                        // the same interval for the repeated dates
                        $diff = null;
                        if ($data['performance']->dateStop !== null) {
                            $diff = $data['performance']->dateStop - $data['performance']->dateStart;
                        }

                        if ($repeater->occurences !== null && $occured < $repeater->occurences) {
                            // add the increased number of occurences
                            for ($i = $occured; $i <= $repeater->occurences; $i++) {
                                $date = array_shift($dates);

                                $performance = clone $data['performance'];
                                $performance->id = 0;
                                $performance->version = 0;
                                $performance->repeater = $repeater;
                                $performance->dateStart = $date;
                                if ($diff !== null) {
                                    $performance->dateStop = $date + $diff;
                                }

                                $this->model->save($performance);
                            }
                        } else {
                            // add the remaining dates until dateUntil
                            while ($date = array_shift($dates)) {
                                $performance = clone $data['performance'];
                                $performance->id = 0;
                                $performance->version = 0;
                                $performance->repeater = $repeater;
                                $performance->dateStart = $date;
                                if ($diff !== null) {
                                    $performance->dateStop = $date + $diff;
                                }

                                $this->model->save($performance);
                            }
                        }
                    }
                } else {
                    $data['performance']->repeater = null;

                    $this->model->save($data['performance']);
                }

                $this->addSuccess('success.data.saved', array('data' => $data['performance']->getDateString()));

                $this->response->setRedirect($this->getAction(self::ACTION_DETAIL, array('id' => $event->id)));

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $referer = $this->request->getQueryParameter('referer');

        $this->templateForm = 'orm/scaffold/form.performance';

        $this->setFormView($form, $referer, $i18n->getLocaleCodeList(), $locale, $event);
    }

    /**
     * Gets a title for the view
     * @param mixed $entry Entry which is being displayed, used only with the
     * form view
     * @return string
     */
    protected function getViewTitle($entry = null) {
        if ($entry) {
            return $entry->name;
        }

        if ($this->translationTitle) {
            return $this->getTranslator()->translate($this->translationTitle);
        }

        return $this->model->getMeta()->getName();
    }

    /**
     * Action to set the calendar settings
     * @return null
     */
    public function settingsAction() {
        $config = $this->getConfig();
        $translator = $this->getTranslator();
        $referer = $this->getReferer();

        // build the form
        $data = array(
            'allowDay' => $config->get('calendar.event.day', true),
            'allowPeriod' => $config->get('calendar.event.period', true),
            'allowRepeat' => $config->get('calendar.event.repeat', true),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('allowDay', 'option', array(
            'label' => $translator->translate('label.calendar.allow.day'),
            'description' => $translator->translate('label.calendar.allow.day.description'),
        ));
        $form->addRow('allowPeriod', 'option', array(
            'label' => $translator->translate('label.calendar.allow.period'),
            'description' => $translator->translate('label.calendar.allow.period.description'),
        ));
        $form->addRow('allowRepeat', 'option', array(
            'label' => $translator->translate('label.calendar.allow.repeat'),
            'description' => $translator->translate('label.calendar.allow.repeat.description'),
        ));
        $form = $form->build();

        // handle the form
        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();

                $config->set('calendar.event.day', $data['allowDay'] ? '1' : '0');
                $config->set('calendar.event.period', $data['allowPeriod'] ? '1' : '0');
                $config->set('calendar.event.repeat', $data['allowRepeat'] ? '1' : '0');

                $this->addSuccess('success.preferences.saved');

                if (!$referer) {
                    $referer = $this->getUrl('system.calendar');
                }

                $this->response->setRedirect($referer);

                return;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('base/settings/calendar', array(
            'form' => $form->getView(),
            'referer' => $referer,
        ));
    }

}
