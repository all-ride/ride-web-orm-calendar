<?php

namespace ride\web\orm\form;

use ride\application\orm\calendar\entry\EventRepeaterEntry;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\reflection\ReflectionHelper;
use ride\library\validation\constraint\ConditionalConstraint;
use ride\library\validation\factory\ValidationFactory;

/**
 * Form to manage a event repeater
 */
class EventRepeaterComponent extends AbstractComponent {

    /**
     * Instance of the reflection helper
     * @var \ride\library\reflection\ReflectionHelper
     */
    protected $reflectionHelper;

    /**
     * Instance of the validation factory
     * @var \ride\library\validation\factory\ValidationFactory
     */
    protected $validationFactory;

    /**
     * Flag to see if a full day selection is possible
     * @var boolean
     */
    protected $allowDay;

    /**
     * Flag to see if a period selection is possible
     * @var boolean
     */
    protected $allowPeriod;

    /**
     * Flag to see if a repeated selection is possible
     * @var boolean
     */
    protected $allowRepeat;

    /**
     * Format for the placeholder of a date field
     * @var string
     */
    protected $dateFormat;

    /**
     * Format for the placeholder of a time field
     * @var string
     */
    protected $timeFormat;

    /**
     * Constructs a new scaffold form component
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function __construct(ReflectionHelper $reflectionHelper, validationFactory $validationFactory) {
        $this->reflectionHelper = $reflectionHelper;
        $this->validationFactory = $validationFactory;
        $this->allowDay = true;
        $this->allowPeriod = true;
        $this->allowRepeat = true;
        $this->dateFormat = 'Y-m-d';
        $this->timeFormat = 'H:i';
    }

    /**
     * Set whether to allow a full day selection
     * @param boolean $allowDay
     * @return null
     */
    public function setAllowDay($allowDay) {
        $this->allowDay = $allowDay;
    }

    /**
     * Set whether to allow a repeater
     * @param boolean $allowRepeat
     * @return null
     */
    public function setAllowPeriod($allowPeriod) {
        $this->allowPeriod = $allowPeriod;
    }

    /**
     * Set whether to allow a repeater
     * @param boolean $allowRepeat
     * @return null
     */
    public function setAllowRepeat($allowRepeat) {
        $this->allowRepeat = $allowRepeat;
    }

    /**
     * Sets the date format for the placeholders of the date fields
     * @param string $dateFormat
     * @return null
     */
    public function setDateFormat($dateFormat) {
        $this->dateFormat = $dateFormat;
    }

    /**
     * Sets the date format for the placeholders of the time fields
     * @param string $timeFormat
     * @return null
     */
    public function setTimeFormat($timeFormat) {
        $this->timeFormat = $timeFormat;
    }

    /**
     * Gets the name of this component, used when this component is the root
     * of the form to be build
     * @return string
     */
    public function getName() {
        return 'form-event-repeater';
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return 'ride\\application\\orm\\calendar\\entry\\EventRepeaterEntry';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        $this->data = $data;

        return array(
            'dateStart' => $this->reflectionHelper->getProperty($data, 'dateStart'),
            'timeStart' => $this->reflectionHelper->getProperty($data, 'timeStart'),
            'dateStop' => $this->reflectionHelper->getProperty($data, 'dateStop'),
            'timeStop' => $this->reflectionHelper->getProperty($data, 'timeStop'),
            'isDay' => $this->reflectionHelper->getProperty($data, 'isDay'),
            'isPeriod' => $this->reflectionHelper->getProperty($data, 'isPeriod'),
            'isRepeat' => $this->reflectionHelper->getProperty($data, 'isRepeat'),
            'mode' => $this->reflectionHelper->getProperty($data, 'mode'),
            'step' => $this->reflectionHelper->getProperty($data, 'step'),
            'weekly' => $this->reflectionHelper->getProperty($data, 'weekly'),
            'monthly' => $this->reflectionHelper->getProperty($data, 'monthly'),
            'until' => $this->reflectionHelper->getProperty($data, 'dateUntil') ? 'date' : ($this->reflectionHelper->getProperty($data, 'occurences') ? 'occurences' : null),
            'dateUntil' => $this->reflectionHelper->getProperty($data, 'dateUntil'),
            'occurences' => $this->reflectionHelper->getProperty($data, 'occurences'),
        );
    }

    /**
     * Parse the form values to data of the component
     * @param array $data
     * @return mixed $data
    */
    public function parseGetData(array $data) {
        if (!$this->data) {
            $class = $this->getDataType();

            $this->data = new $class;
        }

        $this->reflectionHelper->setProperty($this->data, 'dateStart', $data['dateStart']);
        $this->reflectionHelper->setProperty($this->data, 'timeStart', $data['timeStart']);
        if ($this->allowPeriod) {
            $this->reflectionHelper->setProperty($this->data, 'dateStop', $data['dateStop']);
        }
        $this->reflectionHelper->setProperty($this->data, 'timeStop', $data['timeStop']);
        if ($this->allowDay) {
            $this->reflectionHelper->setProperty($this->data, 'isDay', $data['isDay']);
        }
        if ($this->allowPeriod) {
            $this->reflectionHelper->setProperty($this->data, 'isPeriod', $data['isPeriod']);
        }
        if ($this->allowRepeat) {
            $this->reflectionHelper->setProperty($this->data, 'isRepeat', $data['isRepeat']);
            $this->reflectionHelper->setProperty($this->data, 'mode', $data['mode']);
            $this->reflectionHelper->setProperty($this->data, 'step', $data['step']);
            $this->reflectionHelper->setProperty($this->data, 'weekly', $data['weekly']);
            $this->reflectionHelper->setProperty($this->data, 'monthly', $data['monthly']);
            $this->reflectionHelper->setProperty($this->data, 'until', $data['until']);
            $this->reflectionHelper->setProperty($this->data, 'dateUntil', $data['dateUntil']);
            $this->reflectionHelper->setProperty($this->data, 'occurences', $data['occurences']);
        }

        return $this->data;
    }

    /**
     * Prepares the form builder by adding row definitions
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options Extra options from the controller
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];

        $builder->addRow('dateStart', 'date', array(
            'attributes' => array(
                'class' => 'start date',
                'placeholder' => date($this->dateFormat)
            ),
            'round' => true,
        ));
        $builder->addRow('timeStart', 'time', array(
            'attributes' => array(
                'class' => 'start time',
                'placeholder' => date($this->timeFormat),
            ),
        ));

        if ($this->allowPeriod) {
            $builder->addRow('dateStop', 'date', array(
                'attributes' => array(
                    'class' => 'stop date',
                    'placeholder' => date($this->dateFormat)
                ),
                'round' => true,
            ));
        }

        $builder->addRow('timeStop', 'time', array(
            'attributes' => array(
                'class' => 'stop time',
                'placeholder' => date($this->timeFormat),
            ),
        ));

        if ($this->allowDay) {
            $builder->addRow('isDay', 'option', array(
                'label' => '',
                'description' => $translator->translate('label.event.day'),
            ));
        }

        if ($this->allowPeriod) {
            $builder->addRow('isPeriod', 'option', array(
                'label' => '',
                'description' => $translator->translate('label.period'),
            ));
        }

        if ($this->allowRepeat) {
            $builder->addRow('isRepeat', 'option', array(
                'label' => '',
                'description' => $translator->translate('label.repeat'),
            ));
            $builder->addRow('mode', 'select', array(
                'label' => $translator->translate('label.mode'),
                'options' => array(
                    EventRepeaterEntry::MODE_DAILY => $translator->translate('label.daily'),
                    EventRepeaterEntry::MODE_WEEKLY => $translator->translate('label.weekly'),
                    EventRepeaterEntry::MODE_MONTHLY => $translator->translate('label.monthly'),
                    EventRepeaterEntry::MODE_YEARLY => $translator->translate('label.yearly'),
                ),
            ));
            $builder->addRow('step', 'select', array(
                'label' => $translator->translate('label.event.every'),
                'options' => array_combine(range(1, 30), range(1, 30)),
            ));
            $builder->addRow('weekly', 'option', array(
                'label' => $translator->translate('label.event.on'),
                'options' => array(
                    1 => $translator->translate('label.day.monday'),
                    2 => $translator->translate('label.day.tuesday'),
                    3 => $translator->translate('label.day.wednesday'),
                    4 => $translator->translate('label.day.thursday'),
                    5 => $translator->translate('label.day.friday'),
                    6 => $translator->translate('label.day.saturday'),
                    7 => $translator->translate('label.day.sunday'),
                ),
                'multiple' => true,
            ));
            $builder->addRow('monthly', 'option', array(
                'label' => $translator->translate('label.event.by'),
                'options' => array(
                    EventRepeaterEntry::MODE_MONTHLY_DAY_OF_WEEK => $translator->translate('label.day.week'),
                    EventRepeaterEntry::MODE_MONTHLY_DAY_OF_MONTH => $translator->translate('label.day.month'),
                ),
                'default' => 'weekday',
            ));
            $builder->addRow('until', 'option', array(
                'label' => $translator->translate('label.until'),
                'options' => array(
                    'date' => $translator->translate('label.date'),
                    'occurences' => $translator->translate('label.occurences'),
                ),
                'default' => 'weekday',
            ));
            $builder->addRow('occurences', 'integer', array(
                'label' => $translator->translate('label.occurences'),
                'validators' => array(
                    'minmax' => array(
                        'required' => false,
                        'minimum' => 0,
                    ),
                ),
            ));
            $builder->addRow('dateUntil', 'date', array(
                'label' => $translator->translate('label.until'),
                'attributes' => array(
                    'placeholder' => date($this->dateFormat),
                ),
                'round' => true,
            ));

            $requiredValidator = $this->validationFactory->createValidator('required', array());

            $untilDateConstraint = new ConditionalConstraint();
            $untilDateConstraint->addValueCondition('until', 'date');
            $untilDateConstraint->addValidator($requiredValidator, 'dateUntil');

            $untilOccurencesConstraint = new ConditionalConstraint();
            $untilOccurencesConstraint->addValueCondition('until', 'occurences');
            $untilOccurencesConstraint->addValidator($requiredValidator, 'occurences');

            $repeatConstraint = new ConditionalConstraint();
            $repeatConstraint->addValueCondition('isRepeat', '1');
            $repeatConstraint->addValidator($requiredValidator, 'until');
            $repeatConstraint->addConstraint($untilDateConstraint);
            $repeatConstraint->addConstraint($untilOccurencesConstraint);

            $builder->addValidationConstraint($repeatConstraint);
        }
    }

}
