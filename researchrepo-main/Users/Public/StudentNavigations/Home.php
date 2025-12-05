<?php
$conn = new mysqli("sql207.infinityfree.com", "if0_40577910", "CTURepo2025", "if0_40577910_repo_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$mostViewed = [];
$mvSql = "
    SELECT r.id, r.title, r.author, COUNT(v.id) as total_views
    FROM research_documents r
    LEFT JOIN research_views v ON r.id = v.research_id
    WHERE r.status IN ('Active','ApprovedbyAdmin')
    GROUP BY r.id
    ORDER BY total_views DESC
    LIMIT 5
";
$mvResult = $conn->query($mvSql);
if ($mvResult) {
    while ($row = $mvResult->fetch_assoc()) {
        $mostViewed[] = $row;
    }
}

$allFavorites = [];
$afSql = "
    SELECT r.id, r.title, r.author, AVG(rt.rating) as avg_rating, COUNT(rt.rating) as rating_count
    FROM research_documents r
    LEFT JOIN ratings rt ON r.id = rt.research_id
    WHERE r.status IN ('Active','ApprovedbyAdmin')
    GROUP BY r.id
    HAVING rating_count > 0
    ORDER BY avg_rating DESC, rating_count DESC
    LIMIT 5
";
$afResult = $conn->query($afSql);
if ($afResult) {
    while ($row = $afResult->fetch_assoc()) {
        $allFavorites[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cebu Technological University Online Library - Content Section</title>
  <style>
    main { padding: 25px; max-width: 1200px; margin: auto; }
    .info-section { display: flex; ; gap: 6rem; margin-bottom: 8rem; }
    @media(min-width: 640px) { .info-section { flex-direction: row; } }
    .info-card { flex: 1; position: relative; padding: 0.5rem 1.5rem; border-radius: 15px; min-width: 100px; transition: transform 0.2s; }
    .info-card:hover { transform: translateY(-3px); }
    .info-card svg { position: absolute; top: 1rem; right: 1rem; width: 2rem; height: 2rem; opacity: 0.2; }
    .info-card.bg-cyan { background: linear-gradient(to right, #22d3ee, #3b82f6); }
    .info-card.bg-yellow { background: linear-gradient(to right, #facc15, #fcd34d); color: #111827; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .font-semibold { font-weight: 600; }
    .font-light { font-weight: 300; }
    .text-base { font-size: 1rem; }
    header h2 { display: flex; align-items: center; font-weight: 600; font-size: 1.25rem; color: #1f2937; margin-bottom: 1.5rem; }
    header h2 svg { width: 1.25rem; height: 1.25rem; margin-right: 0.5rem; color: #4b5563; }
    .logo-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4rem; margin-bottom: 5rem; }
    .logo-center img { display: block; width: 150px; height: 150px; border-radius: 50%; object-fit: contain; box-shadow: 0 6px 16px rgba(0,0,0,0.15); }
    #searchResults { max-height: 200px; overflow-y: auto; }
    form.search-form { display: flex; width: 100%; max-width: 600px; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    form.search-form input { flex-grow: 1; padding: 0.75rem 1rem; border: none; font-size: 1rem; }
    form.search-form input:focus { outline: 2px solid #f59e0b; }
    form.search-form button { background-color: #f59e0b; color: white; font-weight: 600; padding: 0 1.25rem; cursor: pointer; transition: background-color 0.2s; border: none; }
    form.search-form button:hover { background-color: #d97706; }
    ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem; }
    li { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; transition: box-shadow 0.2s, transform 0.2s; cursor: pointer; background-color: #fff; }
    li:hover { box-shadow: 0 6px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
    li h3 { font-weight: 600; color: #ca8a04; margin: 0 0 0.25rem 0; }
    li p { font-size: 0.875rem; color: #4b5563; margin: 0; }
    #notificationList li { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-bottom: 1px solid #e5e7eb; cursor: pointer; transition: background 0.2s, transform 0.2s; }
    #notificationList li:hover { background: #f3f4f6; transform: translateX(2px); }
    #notificationList li span.icon { font-size: 1rem; width: 20px; display: inline-flex; align-items: center; justify-content: center; }
    @media (max-width: 768px) { main { margin-left: 0; padding: 1rem; } .info-section { flex-direction: column; gap: 1rem; } form.search-form { max-width: 100%; flex-direction: column; } form.search-form button { width: 100%; padding: 0.75rem; } }
    #researchModal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,.6); justify-content:center; align-items:center; }
    #modalContentWrapper { background:white; padding:20px; border-radius:10px; width:70%; max-height:85%; overflow-y:auto; }
    #modalClose { float:right; cursor:pointer; font-size:20px; color:#333; }
  </style>
</head>
<body>
<main>
  <section class="info-section">
    <div class="info-card bg-cyan">
      <div class="text-sm font-semibold" id="currentDate">Loading date...</div>
      <div class="text-xs font-light" id="currentTime">Loading time...</div>
    </div>

  </section>

  <section>
    <div class="logo-center">
      <img src="./Photos/logoCtu.png" alt="Official Seal" />
      <form class="search-form" onsubmit="return false;">
        <input type="search" id="searchInput" placeholder="Search by title, author, category, keywords">
        <button type="submit">Search</button>
      </form>
      <div class="search-dropdown" id="searchResults"></div>
    </div>
  </section>

  <section>
    <header><h2>Most Viewed</h2></header>
    <ul>
      <?php foreach ($mostViewed as $mv): ?>
        <li onclick="openResearchModal(<?= $mv['id'] ?>)">
          <h3><?= htmlspecialchars($mv['title']) ?></h3>
          <p>By <?= htmlspecialchars($mv['author']) ?> | Views: <?= number_format($mv['total_views']) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section>
    <header><h2>All Time Favorites</h2></header>
    <ul>
      <?php foreach ($allFavorites as $af): ?>
        <li onclick="openResearchModal(<?= $af['id'] ?>)">
          <h3><?= htmlspecialchars($af['title']) ?></h3>
          <p>By <?= htmlspecialchars($af['author']) ?> | Rating: <?= round($af['avg_rating'], 1) ?> (<?= $af['rating_count'] ?> ratings)</p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</main>

<!-- Modal -->
<div id="researchModal">
  <div id="modalContentWrapper">
    <span id="modalClose" onclick="closeResearchModal()">&times;</span>
    <div id="modalContent"><p>Loading...</p></div>
  </div>
</div>

<script>
const searchInput=document.getElementById('searchInput');
const resultsDiv=document.getElementById('searchResults');

function updateDateTimePH() {
  const now = new Date();

  const dateOptions = {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'Asia/Manila'
  };
  const timeOptions = {
    hour: 'numeric',
    minute: 'numeric',
    second: 'numeric',
    hour12: true,
    timeZone: 'Asia/Manila'
  };

  document.getElementById('currentDate').textContent =
    now.toLocaleDateString('en-PH', dateOptions);

  document.getElementById('currentTime').textContent =
    now.toLocaleTimeString('en-PH', timeOptions);
}

updateDateTimePH();
setInterval(updateDateTimePH, 1000);

searchInput.addEventListener('input',function(){
  const query=this.value.trim();
  if(!query){resultsDiv.style.display='none';resultsDiv.innerHTML='';return;}
  fetch(`search_resulthome.php?q=${encodeURIComponent(query)}`)
    .then(res=>res.json())
    .then(data=>{
      resultsDiv.innerHTML='';
      if(data.length===0){resultsDiv.style.display='none';return;}
      data.forEach(item=>{
        const span=document.createElement('span');
        span.style.display='block';
        span.style.padding='0.5rem 1rem';
        span.style.cursor='pointer';
        span.style.borderBottom='1px solid #e5e7eb';
        span.innerHTML=`<strong>${item.title}</strong> - ${item.author}`;
        span.onclick=()=>{openResearchModal(item.id);resultsDiv.style.display='none';};
        resultsDiv.appendChild(span);
      });
      resultsDiv.style.display='block';
    });
});

function openResearchModal(id){
  const modal=document.getElementById('researchModal');
  const content=document.getElementById('modalContent');
  modal.style.display='flex';
  content.innerHTML='<p>Loading...</p>';
  fetch('fetch_research_details.php?id='+id)
    .then(res=>res.json())
    .then(data=>{
      if(!data.id){content.innerHTML='<p>Not found.</p>';return;}
      content.innerHTML=`
        <h2>${data.title}</h2>
        <p><strong>Author:</strong> ${data.author}</p>
        <p><strong>Abstract:</strong> ${data.abstract}</p>
        <p><strong>Adviser:</strong> ${data.adviser??'-'}</p>
        <p><strong>Course:</strong> ${data.course??'-'}</p>
        <p><strong>Department:</strong> ${data.department??'-'}</p>
        <p><strong>Category:</strong> ${data.category}</p>
        <p><strong>Views:</strong> ${data.views}</p>
        <p><strong>Keywords:</strong> ${data.keywords??'-'}</p>
        <p><strong>Rating:</strong> ${"★".repeat(Math.round(data.rating))}${"☆".repeat(5-Math.round(data.rating))} (${data.rating} avg)</p>`;
    });
}
function closeResearchModal(){document.getElementById('researchModal').style.display='none';}
</script>
</body>
</html>
