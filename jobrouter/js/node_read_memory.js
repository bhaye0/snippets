const os = require('os');

const calcGigabyte = (val) => {
    return val / (1024 * 1024 * 1024);
}

console.log(`Total Memory:  ${calcGigabyte(os.totalmem())} GB`);
console.log(`Free Memory:   ${calcGigabyte(os.freemem())} GB`);