<x-mail::message>
# Hi {{ $sister->name }}!

Your grocery share for the week of **{{ $week->week_date->format('F j, Y') }}** is ready.

<x-mail::panel>
**Your share: ${{ number_format($amount, 2) }}**

Total grocery bill: ${{ number_format($week->total_amount, 2) }}
Split equally 3 ways
@if ($week->notes)

Notes: {{ $week->notes }}
@endif
</x-mail::panel>

@php $balance = $sister->outstandingTotal(); @endphp
@if ($balance > 0)
**Your current total balance: ${{ number_format($balance, 2) }}** (including this week)

Please send **${{ number_format($balance, 2) }}** when you can.
@else
Your earlier payments already cover this week. You still have **${{ number_format(abs($balance), 2) }}** in credit, so there's nothing to send right now!
@endif

Thanks,<br>
GroceryShare
</x-mail::message>
