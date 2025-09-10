<?php
/**
 * WHMCS Addon Module: TransferInvoice
 * Purpose: Admin utility to transfer an invoice from one client to another safely.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

function transferinvoice_config() {
    return [
        'name' => 'Transfer Invoice',
        'description' => 'Admin utility to transfer an invoice and optionally related transactions to another client.',
        'version' => '1.3',
        'author' => 'Dristi',
        'language' => 'english',
    ];
}

function transferinvoice_activate() { return ['status'=>'success','description'=>'Addon activated']; }
function transferinvoice_deactivate() { return ['status'=>'success','description'=>'Addon deactivated']; }

function transferinvoice_output($vars) {
    if (!isset($_SESSION['adminid'])) { echo '<p><strong>Error:</strong> Admin login required.</p>'; return; }

    // AJAX client search
    if (isset($_GET['ajax'])) {
        if ($_GET['ajax'] === 'clientsearch') {
            $term = trim($_GET['term'] ?? ''); $results = [];
            if ($term !== '') {
                $clients = Capsule::table('tblclients')
                    ->where('id','like',"%$term%")
                    ->orWhere('firstname','like',"%$term%")
                    ->orWhere('lastname','like',"%$term%")
                    ->orWhere('email','like',"%$term%")
                    ->limit(10)->get();
                foreach($clients as $c) { $results[]=['id'=>$c->id,'label'=>"#{$c->id} - {$c->firstname} {$c->lastname} ({$c->email})",'value'=>$c->id]; }
            }
            header('Content-Type: application/json'); echo json_encode($results); exit;
        }
        if ($_GET['ajax'] === 'transactions' && isset($_GET['invoice_id'])) {
            $invoiceId = (int)$_GET['invoice_id'];
            $txns = Capsule::table('tblaccounts')->where('invoiceid',$invoiceId)->get();
            $data=[]; foreach($txns as $t) { $data[]= ['id'=>$t->id,'description'=>$t->description,'amount'=>$t->amount]; }
            header('Content-Type: application/json'); echo json_encode($data); exit;
        }
    }

    // Form submission
    if ($_SERVER['REQUEST_METHOD']==='POST' && check_token('WHMCS.admin.default')) {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $targetClientId = (int)($_POST['target_client_id'] ?? 0);
        $selectedTxns = $_POST['transaction_ids'] ?? [];
        $note = trim($_POST['admin_note'] ?? '');

        $errors=[];
        if($invoiceId<=0) $errors[]='Invalid invoice ID.';
        if($targetClientId<=0) $errors[]='Invalid target client ID.';
        $invoice = Capsule::table('tblinvoices')->where('id',$invoiceId)->first();
        if(!$invoice) $errors[]='Invoice not found.';
        if($invoice && $invoice->userid==$targetClientId) $errors[]='Invoice already belongs to target client.';

        if(!empty($errors)){ echo '<div class="alert alert-danger"><ul>'.implode('',array_map(fn($e)=>"<li>$e</li>",$errors)).'</ul></div>'; }
        else {
            try {
                Capsule::connection()->transaction(function() use ($invoiceId,$targetClientId,$selectedTxns,$note,$invoice){
                    Capsule::table('tblinvoices')->where('id',$invoiceId)->update(['userid'=>$targetClientId]);
                    Capsule::table('tblinvoiceitems')->where('invoiceid',$invoiceId)->update(['userid'=>$targetClientId]);
                    foreach($selectedTxns as $txnId){
                        Capsule::table('tblaccounts')->where('id',(int)$txnId)->update(['userid'=>$targetClientId]);
                    }
                    $adminId=$_SESSION['adminid'] ?? 0;
                    logActivity('[TransferInvoice] Invoice #'.$invoiceId.' moved from Client ID '.$invoice->userid.' to Client ID '.$targetClientId.($selectedTxns? ' | Transactions moved: '.implode(',',$selectedTxns):'').($note? ' | Note: '.$note:''),$adminId);
                });
                echo '<div class="alert alert-success">Invoice #'.$invoiceId.' transferred to client ID '.$targetClientId.'.</div>';
            } catch(Exception $e){ echo '<div class="alert alert-danger">Error: '.$e->getMessage().'</div>'; }
        }
    }

    // Form with autocomplete and styled transaction list
    ?>
    <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
    <script>
    jQuery(function($){
        $('#target_client_search').autocomplete({source:'addonmodules.php?module=TransferInvoice&ajax=clientsearch',minLength:2,select:function(e,ui){$('#target_client_id').val(ui.item.value);}});
        $('#invoice_id').on('change',function(){
            var inv=$(this).val();
            if(inv){
                $.getJSON('addonmodules.php?module=TransferInvoice&ajax=transactions&invoice_id='+inv,function(data){
                    var html='<div><button type="button" id="select_all" class="btn btn-sm btn-secondary mb-2">Select All</button> <button type="button" id="unselect_all" class="btn btn-sm btn-secondary mb-2">Unselect All</button></div>';
                    $.each(data,function(i,t){ html+='<div><input type="checkbox" name="transaction_ids[]" value="'+t.id+'"> '+t.description+' (ID:'+t.id+', Amount:'+t.amount+')</div>';});
                    $('#transaction_list').html(html);
                    $('#select_all').click(function(){$('#transaction_list input[type=checkbox]').prop('checked',true);});
                    $('#unselect_all').click(function(){$('#transaction_list input[type=checkbox]').prop('checked',false);});
                });
            }
        });
    });
    </script>

    <div class="row">
        <div class="col-md-8">
            <h3>Transfer Invoice</h3>
            <form method="post" class="form">
                <?php echo generate_token('WHMCS.admin.default'); ?>
                <div class="form-group"><label>Invoice ID</label><input type="number" name="invoice_id" id="invoice_id" class="form-control" required></div>
                <div class="form-group"><label>Target Client</label><input type="text" id="target_client_search" class="form-control" placeholder="Search by ID, name, or email"><input type="hidden" name="target_client_id" id="target_client_id" required></div>
                <div class="form-group"><label>Transactions to Move (optional)</label><div id="transaction_list" style="max-height:250px;overflow:auto;border:1px solid #ccc;padding:5px;"></div></div>
                <div class="form-group"><label>Admin Note (optional)</label><textarea name="admin_note" class="form-control" rows="3"></textarea></div>
                <button type="submit" class="btn btn-primary">Transfer Invoice</button>
            </form>
        </div>
    </div>
    <?php
}

if(!function_exists('generate_token')){function generate_token($a){return '';}}
if(!function_exists('check_token')){function check_token($a){return true;}}
?>