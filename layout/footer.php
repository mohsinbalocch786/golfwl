</div>

</section>

</div>

<footer class="main-footer">

<strong>Email SMS Blaster System</strong>

</footer>

</div>

<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> -->
 <!-- jQuery (you already have 3.6.0) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>





<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


<!-- Buttons Extension -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<!-- Export dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- Date Range Picker js -->
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>





<!--  Select2 JS -->

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>


<script>
// ── SMS Notification Bell — PHP 5.6 compatible JS ───────────────────────────
(function($){

    var POLL_INTERVAL = 30000;   // 30 s normal
    var FAST_INTERVAL = 8000;    // 8 s after first unread found
    var pollTimer     = null;
    var lastCount     = 0;
    var lastTopId     = 0;
    var UNREAD_URL    = '<?php echo $rel; ?>sms_chat/unread_count.php';
    var isOpen        = false;

    // ── Toggle dropdown ──────────────────────────────────────────────
    $('#notif-bell-btn').on('click', function(e){
        e.stopPropagation();
        isOpen = !isOpen;
        $('#notif-dropdown').toggleClass('open', isOpen);
        if(isOpen && lastCount > 0){
            // Mark read on server when user opens panel
            markAllRead();
        }
    });

    $(document).on('click', function(e){
        if(!$(e.target).closest('#notif-bell-btn, #notif-dropdown').length){
            isOpen = false;
            $('#notif-dropdown').removeClass('open');
        }
    });

    // ── Mark all read ────────────────────────────────────────────────
    $('#notif-mark-all').on('click', function(e){
        e.stopPropagation();
        markAllRead();
    });

    function markAllRead(){
        $.post(UNREAD_URL.replace('unread_count','mark_read'), {}, function(){
            updateBadge(0);
            lastCount = 0;
            $('#notif-mark-all').hide();
        });
    }

    // ── Render notification items ────────────────────────────────────
    function renderItems(items){
        if(!items || items.length === 0){
            $('#notif-empty').show();
            $('#notif-mark-all').hide();
            return;
        }
        $('#notif-empty').hide();
        $('#notif-mark-all').show();

        // Remove old dynamic items
        $('#notif-list .notif-item').remove();

        var html = '';
        for(var i = 0; i < items.length; i++){
            var item = items[i];
            var leadUrl = '<?php echo $rel; ?>leads/view.php?id=' + item.lead_id + '#tab-sms';
            var name = $('<span>').text(item.lead_name).html();
            var from = $('<span>').text(item.from).html();
            var body = $('<span>').text(item.body).html();
            var time = $('<span>').text(item.time).html();
            html += '<a class="notif-item unread" href="' + leadUrl + '">'
                  + '<div class="notif-icon"><i class="fas fa-sms"></i></div>'
                  + '<div class="notif-body">'
                  + '<div class="notif-name">' + name + '</div>'
                  + '<div class="notif-msg">' + body + '</div>'
                  + '<small class="text-muted">' + from + '</small>'
                  + '</div>'
                  + '<div class="notif-time">' + time + '</div>'
                  + '</a>';
        }
        $('#notif-list').prepend(html);
    }

    // ── Update badge count ───────────────────────────────────────────
    function updateBadge(count){
        var $badge = $('#notif-count-badge');
        var $icon  = $('#notif-bell-icon');
        if(count > 0){
            $badge.text(count > 99 ? '99+' : count).css('display','block');
            $icon.css('color','#28a745');
        } else {
            $badge.hide();
            $icon.css('color','#555');
        }
    }

    // ── Shake bell animation ─────────────────────────────────────────
    function shakeBell(){
        var $icon = $('#notif-bell-icon');
        $icon.addClass('bell-shake');
        setTimeout(function(){ $icon.removeClass('bell-shake'); }, 700);
    }

    // ── Main poll ────────────────────────────────────────────────────
    function poll(){
        $.ajax({
            url:      UNREAD_URL,
            type:     'GET',
            dataType: 'json',
            timeout:  10000,
            success:  function(res){
                if(typeof res !== 'object' || res === null) return;

                var count = res.count || 0;

                // New messages arrived since last poll?
                var newArrived = false;
                if(res.recent && res.recent.length > 0 && res.recent[0].id > lastTopId){
                    newArrived = true;
                    lastTopId  = res.recent[0].id;
                }

                if(count !== lastCount || newArrived){
                    updateBadge(count);
                    renderItems(res.recent || []);

                    // If new messages AND dropdown is closed → shake + toast
                    if(newArrived && count > lastCount && !isOpen){
                        shakeBell();
                        if(res.recent && res.recent.length > 0){
                            showToast(res.recent[0]);
                        }
                    }

                    lastCount = count;
                }

                // Speed up polling while there are unread messages
                clearInterval(pollTimer);
                pollTimer = setInterval(poll, count > 0 ? FAST_INTERVAL : POLL_INTERVAL);
            },
            error: function(){}
        });
    }

    // ── Toast notification ───────────────────────────────────────────
    function showToast(msg){
        var leadUrl = '<?php echo $rel; ?>leads/view.php?id=' + msg.lead_id + '#tab-sms';
        var name = $('<span>').text(msg.lead_name).html();
        var from = $('<span>').text(msg.from).html();
        var body = $('<span>').text(msg.body).html();

        var $toast = $('<div>')
            .css({
                position:'fixed', bottom:'24px', right:'24px',
                zIndex:99999, width:'300px', background:'#fff',
                border:'1px solid #dee2e6',
                borderLeft:'4px solid #28a745',
                borderRadius:'8px', padding:'12px 14px',
                boxShadow:'0 6px 20px rgba(0,0,0,.15)',
                cursor:'pointer', fontSize:'.82rem',
                fontFamily:'inherit'
            })
            .html(
                '<div style="display:flex;justify-content:space-between;align-items:flex-start;">'
              + '<div>'
              + '<div style="font-weight:700;color:#28a745;margin-bottom:3px;">'
              + '<i class="fas fa-sms mr-1"></i>New SMS Reply'
              + '</div>'
              + '<div style="font-weight:600;color:#212529;">' + name + '</div>'
              + '<div style="color:#6c757d;">' + from + '</div>'
              + '<div style="color:#495057;margin-top:4px;">' + body + '</div>'
              + '</div>'
              + '<button class="close ml-2" style="font-size:1rem;line-height:1;background:none;border:none;cursor:pointer;color:#aaa;">&times;</button>'
              + '</div>'
            )
            .appendTo('body');

        $toast.find('.close').on('click', function(e){
            e.stopPropagation();
            $toast.fadeOut(200, function(){ $(this).remove(); });
        });
        $toast.on('click', function(){
            window.location.href = leadUrl;
        });

        // Auto-dismiss after 8s
        setTimeout(function(){
            $toast.fadeOut(400, function(){ $(this).remove(); });
        }, 8000);
    }

    // ── Start ─────────────────────────────────────────────────────────
    // First call immediately, then on interval
    $(function(){
        poll();
        pollTimer = setInterval(poll, POLL_INTERVAL);
    });

})(jQuery);
</script>


</body>
<!-- </html> -->