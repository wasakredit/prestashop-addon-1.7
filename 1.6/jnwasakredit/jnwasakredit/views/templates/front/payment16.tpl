{*
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
*}
<section>

  {if $is_error}
    <div class="alert alert-info">
      {$response->data['invalid_properties'][0]['error_message'] nofilter}
    </div>
  {else}
    {$response->data nofilter}
    <script>
        var orderReferences = [
            { key: "partner_checkout_id", value: "{$order_reference_id}" },
            { key: "partner_reserved_order_number", value: "{$order_reference_id}" }
        ];

        var redirect = '{$redirect}';
        var order_reference_id = '{$order_reference_id}';
        var id_cart = '{$id_cart}';

        var options = {
          onComplete: function(orderReferences){
            updateWasa();
          },
          onRedirect: function(orderReferences){
            createOrder(); 
          },
          onCancel: function(orderReferences){
            console.log(orderReferences);
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
            url: '{$ajax}update_id_wasakredit.php',
            data: {order_reference_id: order_reference_id,
                id_cart: id_cart,
                id_wasakredit: id_wasakredit,
                customer_secure_key: {$customer_secure_key}
            },
            success: function(response) {
                if (response){
                    createOrder();
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