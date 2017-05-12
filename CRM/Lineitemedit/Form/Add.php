<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Lineitemedit_Form_Add extends CRM_Core_Form {

  protected $_contributionID;

  protected $_isQuickConfig;

  protected $_fieldNames;

  public function preProcess() {
    $this->_contributionID = CRM_Utils_Request::retrieve('contribution_id', 'Positive', $this);

    $this->_fieldNames = CRM_Lineitemedit_Util::getLineitemFieldNames(TRUE);
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    return array(
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    );
  }

  public function buildQuickForm() {
    foreach ($this->_fieldNames as $fieldName) {
      if ($fieldName == 'line_total') {
        $this->add('text', 'line_total', ts('Total amount'), array(
          'size' => 6,
          'maxlength' => 14,
          'readonly' => TRUE)
        );
        continue;
      }
      elseif ($fieldName == 'price_field_value_id') {
        $options = array('' => '- select any price-field -') + CRM_Lineitemedit_Util::getPriceFieldLists($this->_contributionID);
        $this->add('select', $fieldName, ts('Price Field'), $options, TRUE);
        continue;
      }
      $properties = array(
        'entity' => 'LineItem',
        'name' => $fieldName,
        'context' => 'add',
        'action' => 'create',
      );
      if ($fieldName == 'financial_type_id') {
        CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes);
        $properties['options'] = $financialTypes;
      }
      elseif ($fieldName == 'tax_amount') {
        $properties['readonly'] = TRUE;
      }
      $this->addField($fieldName, $properties, TRUE);
    }

    $this->assign('fieldNames', $this->_fieldNames);

    $this->assign('currency', CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_Currency',
      CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $this->_contributionID, 'currency'),
      'symbol',
      'name'
    ));

    if (in_array('tax_amount', $this->_fieldNames)) {
      $this->assign('taxRates', json_encode(CRM_Core_PseudoConstant::getTaxRates()));
    }

    $this->addFormRule(array(__CLASS__, 'formRule'), $this);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Add Item'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));

    parent::buildQuickForm();
  }

  public static function formRule($fields, $files, $self) {
    $errors = array();

    if ($fields['line_total'] == 0) {
      $errors['line_total'] = ts('Line Total amount should not be empty');
    }
    if ($fields['qty'] == 0) {
      $errors['qty'] = ts('Line quantity cannot be zero');
    }

    return $errors;
  }

  public function postProcess() {
    $values = $this->exportValues();

    $newLineItemParams = array(
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $this->_contributionID,
      'contribution_id' => $this->_contributionID,
      'price_field_id' => CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $values['price_field_value_id'], 'price_field_id'),
      'label' => $values['label'],
      'qty' => $values['qty'],
      'unit_price' => $values['unit_price'],
      'line_total' => $values['line_total'],
      'price_field_value_id' => $values['price_field_value_id'],
      'financial_type_id' => $values['financial_type_id'],
    );

    if (!empty($values['tax_amount']) && $values['tax_amount'] != 0) {
      $newLineItemParams['tax_amount'] = $values['tax_amount'];
    }

    $newLineItem = civicrm_api3('LineItem', 'create', $newLineItemParams);

    // calculate balance, tax and paidamount later used to adjust transaction
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_contributionID);
    $taxAmount = empty($newLineItemParams['tax_amount']) ? 'NULL' : $newLineItemParams['tax_amount'];
    $paidAmount = CRM_Utils_Array::value(
      'paid',
      CRM_Contribute_BAO_Contribution::getPaymentInfo(
        $this->_contributionID,
        'contribution',
        FALSE,
        TRUE
      )
    );

    // Record adjusted amount by updating contribution info and create necessary financial trxns
    $trxn = CRM_Lineitemedit_Util::recordAdjustedAmt(
      $updatedAmount,
      $paidAmount,
      $this->_contributionID,
      $taxAmount,
      NULL
    );

    // record financial item on addition of lineitem
    if ($trxn) {
      CRM_Lineitemedit_Util::insertFinancialItemOnAdd($newLineItem['id'], $taxAmount, $trxn);
    }

    parent::postProcess();
  }

}
