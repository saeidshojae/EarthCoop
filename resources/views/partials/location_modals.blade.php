<!-- Tailwind & Bootstrap CSS via Vite -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
<!-- Modal for adding new region -->
<div class="modal fade" id="addRegionModal" tabindex="-1" aria-labelledby="addRegionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRegionModalLabel">افزودن منطقه جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addRegionForm">
          <div class="mb-3">
            <label for="regionName" class="form-label">نام منطقه</label>
            <input type="text" class="form-control" id="regionName" required>
          </div>
          <input type="hidden" id="regionCityId">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-primary" id="saveRegionBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for adding new neighborhood -->
<div class="modal fade" id="addNeighborhoodModal" tabindex="-1" aria-labelledby="addNeighborhoodModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addNeighborhoodModalLabel">افزودن محله جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addNeighborhoodForm">
          <div class="mb-3">
            <label for="neighborhoodName" class="form-label">نام محله</label>
            <input type="text" class="form-control" id="neighborhoodName" required>
          </div>
          <input type="hidden" id="neighborhoodRegionId">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-primary" id="saveNeighborhoodBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for adding new street -->
<div class="modal fade" id="addStreetModal" tabindex="-1" aria-labelledby="addStreetModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addStreetModalLabel">افزودن خیابان جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addStreetForm">
          <div class="mb-3">
            <label for="streetName" class="form-label">نام خیابان</label>
            <input type="text" class="form-control" id="streetName" required>
          </div>
          <input type="hidden" id="streetNeighborhoodId">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-primary" id="saveStreetBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal for adding new alley -->
<div class="modal fade" id="addAlleyModal" tabindex="-1" aria-labelledby="addAlleyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAlleyModalLabel">افزودن کوچه جدید</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addAlleyForm">
          <div class="mb-3">
            <label for="alleyName" class="form-label">نام کوچه</label>
            <input type="text" class="form-control" id="alleyName" required>
          </div>
          <input type="hidden" id="alleyStreetId">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
        <button type="button" class="btn btn-primary" id="saveAlleyBtn">ذخیره</button>
      </div>
    </div>
  </div>
</div> 