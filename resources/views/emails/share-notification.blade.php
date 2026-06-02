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

Please send **${{ number_format($amount, 2) }}** to cover your share of this week's groceries.

Thanks,<br>
GroceryShare
</x-mail::message>
