<!DOCTYPE html>
<html>
<head>
  <title>Uploads</title>
</head>
<body>

<h2>Your Draft</h2>

<div id="draftContainer"></div>

<button onclick="uploadDraft()">Upload Now</button>

<script>
const container = document.getElementById("draftContainer");

const draft = JSON.parse(localStorage.getItem("draft_subject"));

if (draft) {
  container.innerHTML = `
    <div style="background:#eee; padding:15px;">
      <h3>${draft.name}</h3>
      <p>${draft.desc}</p>
    </div>
  `;
} else {
  container.innerHTML = "<p>No draft found</p>";
}

// SEND TO DATABASE
function uploadDraft() {

  if (!draft) return alert("No draft!");

  fetch("insert_subject.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: `subject_name=${encodeURIComponent(draft.name)}&description=${encodeURIComponent(draft.desc)}`
  })
  .then(() => {
    localStorage.removeItem("draft_subject"); // clear after upload
    alert("Uploaded!");
    location.reload();
  });
}
</script>

</body>
</html>