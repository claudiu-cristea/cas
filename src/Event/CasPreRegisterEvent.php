<?php

namespace Drupal\cas\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\cas\CasPropertyBag;

/**
 * Class CasPreRegisterEvent.
 */
class CasPreRegisterEvent extends Event {

  /**
   * The user information returned from the CAS server.
   *
   * @var \Drupal\cas\CasPropertyBag
   */
  protected $casPropertyBag;

  /**
   * Whether or not to allow automatic registration of this user.
   *
   * @var bool
   */
  public $denyAutomaticRegistration = FALSE;

  /**
   * An array of property values to assign to the user account on registration.
   *
   * @var array
   */
  protected $propertyValues = [];

  /**
   * Contructor.
   *
   * @param \Drupal\cas\CasPropertyBag $cas_property_bag
   *   The CasPropertyBag for context.
   */
  public function __construct(CasPropertyBag $cas_property_bag) {
    $this->casPropertyBag = $cas_property_bag;
  }

  /**
   * Return the CasPropertyBag of the event.
   *
   * @return \Drupal\cas\CasPropertyBag
   *   The $casPropertyBag property.
   */
  public function getCasPropertyBag() {
    return $this->casPropertyBag;
  }

  /**
   * Getter for propertyValues.
   *
   * @return array
   *   The user property values.
   */
  public function getPropertyValues() {
    return $this->propertyValues;
  }

  /**
   * Set a single property value for the user entity on registration.
   *
   * @param string $property
   *   The user entity property to set.
   * @param mixed $value
   *   The value of the property.
   */
  public function setPropertyValue($property, $value) {
    $this->propertyValues[$property] = $value;
  }

  /**
   * Set an array of property values for the user entity on registration.
   *
   * @param array $property_values
   *   The property values to set with each key corresponding to the property.
   */
  public function setPropertyValues(array $property_values) {
    $this->propertyValues = array_merge($this->propertyValues, $property_values);
  }

}
