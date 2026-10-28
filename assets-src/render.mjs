/**
 * Renders the wordpress.org banner and settings screenshots with headless
 * Chrome over the DevTools protocol (Node 22+, no dependencies).
 *
 * Usage: node assets-src/render.mjs <devtools-port> <job.json>
 * Each job: { url, out, width, height, scale, cookies?, clip? } where clip is
 * a JavaScript expression returning { x, y, width, height } in CSS pixels.
 */
import { readFileSync, writeFileSync } from 'node:fs';

const [ , , port, jobFile ] = process.argv;
const jobs = JSON.parse( readFileSync( jobFile, 'utf8' ) );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

let tab;
for ( let i = 0; i < 40 && ! tab; i++ ) {
	try {
		tab = await ( await fetch( `http://127.0.0.1:${ port }/json/new?about:blank`, { method: 'PUT' } ) ).json();
	} catch {
		await sleep( 250 );
	}
}
const ws = new WebSocket( tab.webSocketDebuggerUrl );
await new Promise( ( r ) => ws.addEventListener( 'open', r ) );
let id = 0;
const pending = new Map();
ws.addEventListener( 'message', ( { data } ) => {
	const msg = JSON.parse( data );
	if ( msg.id && pending.has( msg.id ) ) {
		pending.get( msg.id )( msg );
		pending.delete( msg.id );
	}
} );
const send = ( method, params = {} ) => new Promise( ( resolve, reject ) => {
	const n = ++id;
	pending.set( n, ( msg ) => ( msg.error ? reject( new Error( `${ method }: ${ msg.error.message }` ) ) : resolve( msg.result ) ) );
	ws.send( JSON.stringify( { id: n, method, params } ) );
} );
const evaluate = async ( expression ) => ( await send( 'Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true } ) ).result.value;

await send( 'Page.enable' );
await send( 'Network.enable' );
for ( const job of jobs ) {
	for ( const cookie of job.cookies || [] ) {
		await send( 'Network.setCookie', cookie );
	}
	await send( 'Emulation.setDeviceMetricsOverride', { width: job.width, height: job.height, deviceScaleFactor: job.scale || 1, mobile: false } );
	await send( 'Page.navigate', { url: job.url } );
	for ( let i = 0; i < 100 && ( await evaluate( 'document.readyState' ) ) !== 'complete'; i++ ) {
		await sleep( 100 );
	}
	await evaluate( 'document.fonts.ready.then(() => true)' );
	await sleep( 300 );
	const clip = job.clip ? await evaluate( job.clip ) : { x: 0, y: 0, width: job.width, height: job.height };
	const shot = await send( 'Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { ...clip, scale: 1 } } );
	writeFileSync( job.out, Buffer.from( shot.data, 'base64' ) );
	console.log( `${ job.out } (${ Math.round( clip.width * ( job.scale || 1 ) ) }x${ Math.round( clip.height * ( job.scale || 1 ) ) })` );
}
ws.close();
process.exit( 0 );
