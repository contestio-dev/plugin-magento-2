<?php
namespace Contestio\Connect\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ToggleShow implements ArrayInterface
{
  /**
   * Toggle show options: 'Afficher' or 'Masquer'
   * 
   * @return array
   */
  public function toOptionArray()
  {
    return [
      ['value' => 'show', 'label' => __('Afficher')],
      ['value' => 'hide', 'label' => __('Masquer')]
    ];
  }
}

?>