<?php
require_once 'config.php';
requireLogin();
 $uid=$_SESSION['user_id']; $cid=(int)($_GET['id']??0);
 $s=$pdo->prepare("SELECT c.*,l.title ad_title,l.price ad_price,l.id lid,u1.name buyer_name,u2.name seller_name,w.id wid,w.category wcat,w.rate_type wrt,w.rate wrate,u3.name worker_name FROM conversations c LEFT JOIN listings l ON l.id=c.listing_id LEFT JOIN workers w ON w.id=c.worker_id LEFT JOIN users u3 ON u3.id=w.user_id JOIN users u1 ON u1.id=c.buyer_id JOIN users u2 ON u2.id=c.seller_id WHERE c.id=? AND (c.buyer_id=? OR c.seller_id=?)");
 $s->execute([$cid,$uid,$uid]); $conv=$s->fetch();
if (!$conv) { flash('Conversation not found.','error'); header('Location: messages.php'); exit; }
 $isBuyer=($conv['buyer_id']==$uid);
 $otherName=$isBuyer?($conv['wid']?$conv['worker_name']:$conv['seller_name']):$conv['buyer_name'];
 $pdo->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id<>? AND is_read=0")->execute([$cid,$uid]);
 $s=$pdo->prepare("SELECT id,message,created_at,(sender_id=?) AS mine FROM messages WHERE conversation_id=? ORDER BY id ASC");
 $s->execute([$uid,$cid]); $all=$s->fetchAll();
 $lastId=0; foreach($all as $m) $lastId=max($lastId,(int)$m['id']);
require 'includesheader.php';
?>
<style>
.chat-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding-bottom:14px;border-bottom:1px solid #e2e8f0;margin-bottom:14px;}
.chat-head .who{font-size:1.15rem;font-weight:700;}.chat-head .adref{font-size:.85rem;color:#64748b;}
.chat-box{height:55vh;min-height:320px;overflow-y:auto;padding:18px;background:#f1f5f9;border-radius:10px;display:flex;flex-direction:column;gap:10px;}
.msg{max-width:75%;padding:10px 14px;border-radius:14px;font-size:.93rem;word-wrap:break-word;line-height:1.45;}
.msg.mine{align-self:flex-end;background:var(--primary);color:#fff;border-bottom-right-radius:4px;}
.msg.them{align-self:flex-start;background:#fff;border-bottom-left-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.msg .t{display:block;font-size:.7rem;opacity:.7;margin-top:4px;}
.chat-input{display:flex;gap:10px;margin-top:14px;}
.chat-input input{flex:1;padding:13px 15px;border:1px solid #cbd5e1;border-radius:10px;font-size:.95rem;outline:none;}
.chat-input button{padding:13px 24px;}
.live-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:6px;animation:pulse 1.5s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
</style>
<div class="card">
<div class="chat-head"><div><div class="who">💬 <?= htmlspecialchars($otherName) ?></div>
<div class="adref">Re: <?php if ($conv['wid']): ?>🔧 <a href="worker.php?id=<?= $conv['wid'] ?>"><b><?= htmlspecialchars($conv['wcat']) ?></b></a> · <?= rateLabel($conv['wrt'],$conv['wrate']) ?>
<?php else: ?><a href="listing.php?id=<?= $conv['lid'] ?>"><b><?= htmlspecialchars($conv['ad_title']) ?></b></a> · ₹<?= number_format((float)$conv['ad_price']) ?><?php endif; ?></div></div>
<div class="adref"><span class="live-dot"></span>Live — auto-refresh 3s</div></div>
<div class="chat-box" id="chatBox">
<?php if(!$all): ?><p class="muted" style="text-align:center;margin:auto;">No messages yet — say hello! 👋</p><?php endif; ?>
<?php foreach($all as $m): ?><div class="msg <?= $m['mine']?'mine':'them' ?>"><?= nl2br(htmlspecialchars($m['message'])) ?><span class="t"><?= date('d M, h:i A',strtotime($m['created_at'])) ?></span></div><?php endforeach; ?>
</div>
<form class="chat-input" id="chatForm"><input type="text" id="msgInput" placeholder="Type your message..." maxlength="1000" autocomplete="off" required><button class="btn btn-accent">Send ➤</button></form>
<p class="muted" style="margin-top:8px;">⚠️ Never share OTPs or bank details.</p></div>
<script>
let lastId=<?= $lastId ?>; const box=document.getElementById('chatBox'); box.scrollTop=box.scrollHeight;
function nl2br(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML.replace(/\n/g,'<br>');}
function addMsg(m){const div=document.createElement('div');div.className='msg '+(m.mine?'mine':'them');div.innerHTML=nl2br(m.text)+'<span class="t">'+m.time+'</span>';box.appendChild(div);box.scrollTop=box.scrollHeight;}
setInterval(function(){fetch('api/chat-fetch.php?cid=<?= $cid ?>&after='+lastId).then(r=>r.json()).then(d=>{(d.messages||[]).forEach(m=>{addMsg(m);lastId=Math.max(lastId,m.id);});}).catch(()=>{});},3000);
document.getElementById('chatForm').addEventListener('submit',function(e){e.preventDefault();
const inp=document.getElementById('msgInput');const text=inp.value.trim();if(!text)return;
const btn=this.querySelector('button');btn.disabled=true;
const fd=new FormData();fd.append('cid','<?= $cid ?>');fd.append('message',text);
fetch('api/chat-send.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
if(d.ok){addMsg({mine:1,text:text,time:d.time});lastId=Math.max(lastId,d.id);inp.value='';inp.focus();}
else if(d.error==='rate')alert('Too fast — wait a moment.');else if(d.error==='login')location.href='login.php';
}).finally(()=>btn.disabled=false);});
</script>
<?php require 'includesfooter.php'; ?>