/**
 * 2008 - 2017 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @version   1.0.0
 * @author    Jarda Nalezny <jaroslav@nalezny.cz>
 * @link      http://www.presto-changeo.com
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */
 
$( document ).ready(function() {
    if (prestashop.page.page_name == 'search' || prestashop.page.page_name == 'category'){
        product_ids = [];

        $("#js-product-list article").each(function( index ) {
            product_ids.push($(this).data('id-product'));
        });

        updateproductlist(product_ids);

        prestashop.on('updateProductList', (data) => {
          updateproductlist(product_ids);
        });
    }

    if (prestashop.page.page_name == 'checkout'){
      //console.log($('[name="method_payment"]:checked').val());

      // $("body").on('DOMSubtreeModified', "#onepagecheckoutps_step_three_container", function() {
      //   setTimeout(function(){updateleasingplans()}, 3000);
      // });

      $("body").on('DOMSubtreeModified', "#onepagecheckoutps_step_three", function() {
          updatewasabutton();
      });
    }


   
});

function updatewasabutton(){
  if ($('[name="method_payment"]:checked').val() == 'jn_wasakredit'){
    $('#btn_place_order').html('<i class="fa-pts fa-pts-shopping-cart"></i>&nbsp;&nbsp;Gå vidare');
  }else{
    $('#btn_place_order').html('<i class="fa-pts fa-pts-shopping-cart"></i>&nbsp;&nbsp;Slutför beställning');
  }
}

function updateleasingplans(amount){
  $.ajax({
      type: "POST",
      url: ajax_url+'get_totalleasing.php',
      data: { total: amount},
      success: function(response) {
          if (response){
            contract_table = '<h2>Wasa Kredit Leasing</h2><ul>';
            results = $.parseJSON(response);
            $(results).each(function( index ) {
              data = $(this);
              contract = data[0];
              contract_table += '<li>'+contract['monthly_cost']['amount']+' kr/mån ('+contract['contract_length']+' mån)</li>';
            });
            contract_table += '</ul>';
            $('[value="jn_wasakredit"]').parent().parent().find('.payment_content').html(contract_table);
          }
      }
  });
  return true;
}

function updateproductlist(product_ids){

  $( "article.product-miniature .product-price-and-shipping" ).each(function( index ) {
      $( "<p class='leasing'></p>" ).appendTo($(this));
  });

  $.ajax({
      type: "POST",
      url: ajax_url+'get_productlist.php',
      data: { product_ids: product_ids},
      success: function(response) {
          if (response){
            results = $.parseJSON(response);
            $(results).each(function( index ) {
              data = $(this);
              product = data[0];
              leasable = product['leasable'];
              if (leasable == true){
                amount = product['monthly_cost']['amount'];
                product_id = product['product_id'];
                  $('*[data-id-product="'+product_id+'"] .product-meta .product-price-and-shipping p.leasing').text("Leasing "+amount+" kr/mån");
              }
            });
          }
      }
  });
  return true;
}



