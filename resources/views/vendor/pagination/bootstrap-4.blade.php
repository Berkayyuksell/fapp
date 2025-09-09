@if ($paginator->hasPages())
    <div class="text-center mt-3">
        <p class="small text-muted mb-0">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} results
        </p>
    </div>
@endif
