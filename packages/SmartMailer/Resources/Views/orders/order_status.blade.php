@if($order->status == 8)
@include('emails.order.components.status_defer')
@elseif($order->status == 5)
@include('emails.order.components.status_reject')
@elseif($order->status == 4)
@include('emails.order.components.status_success')
@endif
