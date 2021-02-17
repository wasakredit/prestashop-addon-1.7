{*
 * 2008 - 2021 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @version   1.0.0
 * @author    Wasa Kredit AB
 * @link      http://www.wasakredit.se
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
*}

{extends "$layout"}
{block name="content"}
<section>
  {if $error} 
    <div class="alert alert-info">
      {$response}
    </div>
  {else}
  {$iframe nofilter}
    <script>
        var orderReferences = [
            { key: "partner_checkout_id", value: "{$order_reference_id}" },
            { key: "partner_reserved_order_number", value: "{$order_reference_id}" }
        ];

        var redirect = '{$redirect}';
        var order_reference_id = '{$order_reference_id}';
        var id_cart = '{$id_cart}';
        var secure_key = '{$secure_key}';
        var update_wasaid = 'update_wasaid';

        var options = {
            onComplete: function(orderReferences){
                updateWasa();
            },
            onRedirect: function(orderReferences){
                createOrder(); 
            },
            onCancel: function(orderReferences){
                window.location.href = 'index.php?controller=order&step=1';
            }
        };   

        window.wasaCheckout.init(options);

        setTimeout(function(){
            id_wasakredit = $('#wasaIframe').attr('src').split("id=")[1];
        }, 1000);

        function updateWasa(){

          $.ajax({
              type: "POST",
              url: '{$ajax}',
              data: {
                order_reference_id: order_reference_id,
                id_cart: id_cart,
                id_wasakredit: id_wasakredit,
                secure_key: secure_key,
                action: update_wasaid
              },
              success: function(response) {
                  if (response) {
                    response = $.parseJSON(response);
                    if (response['progress'] == 'go') {
                        createOrder();  
                    }else{
                        window.location.href = 'index.php?controller=order&step=1';
                    }
                  }
              }
          });
          return true;
        }

        function createOrder(){
          window.location.href = redirect;
        }

    </script>
  {/if}
</section>
{/block}
