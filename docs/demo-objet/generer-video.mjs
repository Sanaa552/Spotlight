import { spawn } from 'node:child_process';
import { readFile, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';

const directory = resolve(import.meta.dirname);
const chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const profile = join(directory, '.chrome-video-temp');
const photo = join(directory, 'photo-decouverte-portefeuille.jpg');
const video = join(directory, 'SIMULATION-video-decouverte.webm');
const browser = spawn(chrome, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' });

const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

try {
  let port;
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      port = Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]);
      if (port) break;
    } catch { /* Chrome is still starting. */ }
    await sleep(100);
  }
  if (!port) throw new Error('Chrome did not start its local debugging endpoint.');

  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  const target = targets.find(item => item.type === 'page');
  if (!target) throw new Error('Chrome did not open a page.');

  const socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolveOpen, rejectOpen) => {
    socket.addEventListener('open', resolveOpen, { once: true });
    socket.addEventListener('error', rejectOpen, { once: true });
  });

  let id = 0;
  const pending = new Map();
  socket.addEventListener('message', event => {
    const reply = JSON.parse(event.data);
    if (!pending.has(reply.id)) return;
    const { resolve: finish, reject } = pending.get(reply.id);
    pending.delete(reply.id);
    if (reply.error || reply.result?.exceptionDetails) reject(new Error(JSON.stringify(reply.error ?? reply.result.exceptionDetails)));
    else finish(reply.result);
  });

  const photoBase64 = (await readFile(photo)).toString('base64');
  const expression = `(async () => {
    const image = new Image();
    image.src = 'data:image/jpeg;base64,${photoBase64}';
    await image.decode();
    const canvas = document.createElement('canvas');
    canvas.width = 800;
    canvas.height = 600;
    const context = canvas.getContext('2d');
    let frame = 0;
    const draw = () => {
      const zoom = 1 + frame * 0.0007;
      const width = canvas.width * zoom;
      const height = canvas.height * zoom;
      context.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
      context.fillStyle = 'rgba(0, 0, 0, 0.83)';
      context.fillRect(0, 0, canvas.width, 54);
      context.fillStyle = '#ffffff';
      context.font = 'bold 28px Arial';
      context.fillText('SIMULATION - TEST', 20, 36);
      frame++;
    };
    draw();
    const type = MediaRecorder.isTypeSupported('video/webm;codecs=vp8')
      ? 'video/webm;codecs=vp8' : 'video/webm';
    const recorder = new MediaRecorder(canvas.captureStream(10), { mimeType: type });
    const chunks = [];
    recorder.ondataavailable = event => { if (event.data.size) chunks.push(event.data); };
    const stopped = new Promise((resolveStop, rejectStop) => {
      recorder.onstop = resolveStop;
      recorder.onerror = rejectStop;
    });
    recorder.start();
    const timer = setInterval(draw, 100);
    await new Promise(done => setTimeout(done, 4100));
    clearInterval(timer);
    recorder.stop();
    await stopped;
    const bytes = new Uint8Array(await new Blob(chunks, { type }).arrayBuffer());
    let binary = '';
    for (let offset = 0; offset < bytes.length; offset += 32768) {
      binary += String.fromCharCode(...bytes.subarray(offset, offset + 32768));
    }
    return btoa(binary);
  })()`;

  const result = await new Promise((finish, reject) => {
    const requestId = ++id;
    pending.set(requestId, { resolve: finish, reject });
    socket.send(JSON.stringify({ id: requestId, method: 'Runtime.evaluate', params: {
      expression, awaitPromise: true, returnByValue: true,
    } }));
  });
  const encoded = result.result?.value;
  if (!encoded) throw new Error('Chrome did not return the recorded video.');
  const bytes = Buffer.from(encoded, 'base64');
  await writeFile(video, bytes);
  console.log(`${video} (${bytes.length} bytes)`);
  socket.close();
} finally {
  browser.kill();
}
