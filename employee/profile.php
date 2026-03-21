<script>
// Password strength checker
document.getElementById('np').addEventListener('input', function(){
    var v = this.value, score = 0, 
        bar = document.getElementById('strength-bar'), 
        lbl = document.getElementById('strength-label'),
        btn = document.querySelector('button[type="submit"]');
    
    if(v.length >= 6) score++;
    if(v.length >= 10) score++;
    if(/[A-Z]/.test(v)) score++;
    if(/[0-9]/.test(v)) score++;
    if(/[^A-Za-z0-9]/.test(v)) score++;
    
    var colors = ['#e53935','#e53935','#fb8c00','#f9a825','#2e7d32'];
    var labels = ['','Very Weak','Weak','Good','Strong'];
    
    bar.style.width = (score * 20) + '%';
    bar.style.background = colors[score] || '#e53935';
    lbl.textContent = labels[score] || '';
    lbl.style.color = colors[score] || '#e53935';
    
    // Enable button only if 6+ characters
    if(v.length >= 6){
        btn.disabled = false;
        btn.style.opacity = '1';
    } else {
        btn.disabled = true;
        btn.style.opacity = '0.5';
    }
});

// Initially disable button
window.onload = function(){
    var btn = document.querySelector('form [name="action"][value="password"]')
        .closest('form')
        .querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.style.opacity = '0.5';
}
</script>