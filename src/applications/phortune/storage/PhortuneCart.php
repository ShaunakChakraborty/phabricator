<?php

final class PhortuneCart extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_READY = 'cart:ready';
  const STATUS_PURCHASING = 'cart:purchasing';
  const STATUS_PURCHASED = 'cart:purchased';

  protected $accountPHID;
  protected $authorPHID;
  protected $status;
  protected $metadata;

  private $account = self::ATTACHABLE;
  private $purchases = self::ATTACHABLE;

  public static function initializeNewCart(
    PhabricatorUser $actor,
    PhortuneAccount $account) {
    $cart = id(new PhortuneCart())
      ->setAuthorPHID($actor->getPHID())
      ->setStatus(self::STATUS_READY)
      ->setAccountPHID($account->getPHID());

    $cart->account = $account;
    $cart->purchases = array();

    return $cart;
  }

  public function newPurchase(
    PhabricatorUser $actor,
    PhortuneProduct $product) {

    $purchase = PhortunePurchase::initializeNewPurchase($actor, $product)
      ->setAccountPHID($this->getAccount()->getPHID())
      ->setCartPHID($this->getPHID())
      ->save();

    $this->purchases[] = $purchase;

    return $purchase;
  }

  public function getCheckoutURI() {
    return '/phortune/cart/'.$this->getID().'/checkout/';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_account' => array(
          'columns' => array('accountPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CART);
  }

  public function attachPurchases(array $purchases) {
    assert_instances_of($purchases, 'PhortunePurchase');
    $this->purchases = $purchases;
    return $this;
  }

  public function getPurchases() {
    return $this->assertAttached($this->purchases);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function getTotalPriceAsCurrency() {
    $prices = array();
    foreach ($this->getPurchases() as $purchase) {
      $prices[] = $purchase->getTotalPriceAsCurrency();
    }

    return PhortuneCurrency::newFromList($prices);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getAccount()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getAccount()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Carts inherit the policies of the associated account.');
  }

}
